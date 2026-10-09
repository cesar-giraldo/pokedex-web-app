<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Admin\Service\Pdf\LegalDocumentPdfExporter;
use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\NotificationType;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\LegalDocumentVersion;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserLegalAcceptance;
use App\Legal\LegalDocumentPublisher;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function array_unique;
use function array_values;
use function sprintf;

#[Group('functional')]
final class LegalDocumentControllerTest extends WebTestCase
{
    use AdminAuthenticatedClientTrait;

    private ?EntityManagerInterface $entityManager = null;

    private ?int $versionId = null;

    /** @var list<int> */
    private array $versionIds = [];

    private string $notificationTitle = '';

    /** @var list<string> */
    private array $notificationTitles = [];

    protected function tearDown(): void
    {
        if ($this->entityManager instanceof EntityManagerInterface && $this->entityManager->isOpen()) {
            $titles = $this->notificationTitles;

            if ('' !== $this->notificationTitle) {
                $titles[] = $this->notificationTitle;
            }

            foreach (array_values(array_unique($titles)) as $title) {
                $notifications = $this->entityManager->getRepository(Notification::class)->findBy([
                    'type' => NotificationType::LegalVersionPublished,
                    'title' => $title,
                ]);

                foreach ($notifications as $notification) {
                    $this->entityManager->remove($notification);
                }
            }

            $versionIds = $this->versionIds;

            if (null !== $this->versionId) {
                $versionIds[] = $this->versionId;
            }

            foreach (array_values(array_unique($versionIds)) as $versionId) {
                $version = $this->entityManager->find(LegalDocumentVersion::class, $versionId);

                if ($version instanceof LegalDocumentVersion) {
                    $acceptances = $this->entityManager->getRepository(UserLegalAcceptance::class)->findBy([
                        'version' => $version,
                    ]);

                    foreach ($acceptances as $acceptance) {
                        $this->entityManager->remove($acceptance);
                    }

                    $this->entityManager->flush();
                    $this->entityManager->remove($version);
                }
            }

            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testDeveloperCanOpenTheLegalModule(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);

        $client->request('GET', '/admin/legal');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Documentos vigentes');

        $client->request('GET', '/admin/legal/privacy_policy');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('nav', 'Legal');
        self::assertSelectorTextContains('body', 'Historial');
    }

    public function testSavingADraftReturnsToTheVersionHistoryWithASuccessMessage(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        /** @var LegalDocumentPublisher $publisher */
        $publisher = static::getContainer()->get(LegalDocumentPublisher::class);
        $publisher->ensureDocuments();

        /** @var LegalDocumentRepository $documents */
        $documents = static::getContainer()->get(LegalDocumentRepository::class);
        $document = $documents->findOneByType(LegalDocumentType::TermsOfUse);
        self::assertNotNull($document);

        $draft = $publisher->startDraft($document, $developer);
        $this->versionId = $draft->getId();

        $crawler = $client->request('GET', '/admin/legal/terms_of_use/versions/' . $draft->getVersionNumber() . '/edit');
        self::assertResponseIsSuccessful();

        $client->submit($crawler->selectButton('Guardar borrador')->form());

        self::assertResponseRedirects('/admin/legal/terms_of_use');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Borrador guardado.');
        self::assertSelectorTextContains('body', 'Historial');
    }

    public function testDeletingADraftReturnsToTheVersionHistoryWithASuccessMessage(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        /** @var LegalDocumentPublisher $publisher */
        $publisher = static::getContainer()->get(LegalDocumentPublisher::class);
        $publisher->ensureDocuments();

        /** @var LegalDocumentRepository $documents */
        $documents = static::getContainer()->get(LegalDocumentRepository::class);
        $document = $documents->findOneByType(LegalDocumentType::PrivacyPolicy);
        self::assertNotNull($document);

        $draft = $publisher->startDraft($document, $developer);
        $this->versionId = $draft->getId();

        $crawler = $client->request('GET', '/admin/legal/privacy_policy/versions/' . $draft->getVersionNumber() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Eliminar borrador');

        $client->submit($crawler->filter('form[action$="/delete"]')->form());

        self::assertResponseRedirects('/admin/legal/privacy_policy');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Borrador eliminado.');
        self::assertSelectorTextContains('body', 'Historial');
    }

    public function testOperatorCannotManageLegalDocuments(): void
    {
        $client = static::createClient();
        $this->loginAsOperator($client);

        $client->request('GET', '/admin/legal');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPublishNotifiesActiveUsersAndBlocksUntilAccepted(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->publishPrivacyPolicy($developer);

        $client->request('GET', '/admin/home');
        self::assertResponseRedirects('/admin/legal/accept');

        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Acepto la política de prueba');

        $form = $crawler->selectButton('Aceptar y continuar')->form();
        $accepted = $form['accepted'];
        self::assertInstanceOf(ChoiceFormField::class, $accepted);
        $accepted->tick();
        $client->submit($form);
        self::assertResponseRedirects('/admin/legal/accept');

        $client->followRedirect();
        self::assertResponseRedirects('/admin/home');

        $client->request('GET', '/legal/privacy_policy');
        self::assertResponseRedirects('/es/privacidad');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Política de prueba');
    }

    public function testOpeningAnAcceptedLegalNotificationShowsThatPublicVersion(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $privacy = $this->publishDocument(
            $developer,
            LegalDocumentType::PrivacyPolicy,
            'Política de prueba',
            'Acepto la política de prueba',
        );
        $privacyTitle = $this->notificationTitle;
        $this->publishDocument(
            $developer,
            LegalDocumentType::TermsOfUse,
            'Términos de prueba',
            'Acepto los términos de prueba',
        );

        $notification = $this->entityManager->getRepository(Notification::class)->findOneBy([
            'recipient' => $developer,
            'title' => $privacyTitle,
        ]);
        self::assertInstanceOf(Notification::class, $notification);

        $crawler = $client->request('GET', '/admin/legal/accept');
        $token = $crawler->filter('form[action$="/open"] input[name="_token"]')->attr('value');
        self::assertIsString($token);
        $openUrl = sprintf('/admin/notifications/%d/open', $notification->getId());

        $client->request('POST', $openUrl, ['_token' => $token]);
        self::assertResponseRedirects('/admin/legal/accept');

        $crawler = $client->followRedirect();
        $form = $crawler->selectButton('Aceptar y continuar')->form();
        $accepted = $form['accepted'];
        self::assertInstanceOf(ChoiceFormField::class, $accepted);
        $accepted->tick();
        $client->submit($form);

        $client->request('POST', $openUrl, ['_token' => $token]);
        self::assertResponseRedirects(sprintf('/legal/privacy_policy/%d?lang=es', $privacy->getVersionNumber()));

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Política de prueba');

        $client->request('GET', '/admin/home');
        self::assertResponseRedirects('/admin/legal/accept');
    }

    public function testAcceptanceListShowsWhoAcceptedAPublishedVersion(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        /** @var LegalDocumentPublisher $publisher */
        $publisher = static::getContainer()->get(LegalDocumentPublisher::class);
        $publisher->ensureDocuments();

        /** @var LegalDocumentRepository $documents */
        $documents = static::getContainer()->get(LegalDocumentRepository::class);
        $terms = $documents->findOneByType(LegalDocumentType::TermsOfUse);
        self::assertNotNull($terms);
        $draft = $publisher->startDraft($terms, $developer);
        $draftId = $draft->getId();
        self::assertNotNull($draftId);
        $this->versionId = $draftId;
        $this->versionIds[] = $draftId;

        $crawler = $client->request('GET', '/admin/legal/terms_of_use');
        self::assertResponseIsSuccessful();
        $draftRow = $crawler->filter('tbody tr')->reduce(
            static fn (Crawler $row): bool => 'v' . $draft->getVersionNumber() === trim($row->filter('td')->eq(0)->text()),
        );
        self::assertCount(1, $draftRow);
        self::assertStringNotContainsString('Aceptaciones', $draftRow->text());

        $privacy = $this->publishDocument(
            $developer,
            LegalDocumentType::PrivacyPolicy,
            'Política de prueba',
            'Acepto la política de prueba',
        );

        $crawler = $client->request('GET', '/admin/legal/accept');
        $form = $crawler->selectButton('Aceptar y continuar')->form();
        $accepted = $form['accepted'];
        self::assertInstanceOf(ChoiceFormField::class, $accepted);
        $accepted->tick();
        $client->submit($form);

        $crawler = $client->request('GET', '/admin/legal/privacy_policy');
        self::assertResponseIsSuccessful();
        $acceptancesLink = sprintf('/admin/legal/privacy_policy/versions/%d/acceptances', $privacy->getVersionNumber());
        self::assertGreaterThan(0, $crawler->filter(sprintf('a[href$="%s"]', $acceptancesLink))->count());

        $client->request('GET', $acceptancesLink);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Functional Developer');
        self::assertSelectorTextContains('body', '@tst-devel');
        self::assertSelectorTextContains('body', 'Developer');
        self::assertSelectorTextContains('body', 'Aceptación obligatoria');
        self::assertSelectorTextContains('body', 'Español - es');
        self::assertSelectorTextNotContains('body', 'Nadie ha aceptado esta versión.');
    }

    public function testPublishedPreviewDownloadsAPdfAndDraftsDoNot(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        /** @var LegalDocumentPublisher $publisher */
        $publisher = static::getContainer()->get(LegalDocumentPublisher::class);
        $publisher->ensureDocuments();

        /** @var LegalDocumentRepository $documents */
        $documents = static::getContainer()->get(LegalDocumentRepository::class);
        $terms = $documents->findOneByType(LegalDocumentType::TermsOfUse);
        self::assertNotNull($terms);
        $draft = $publisher->startDraft($terms, $developer);
        $draftId = $draft->getId();
        self::assertNotNull($draftId);
        $this->versionId = $draftId;
        $this->versionIds[] = $draftId;

        $draftPreview = sprintf('/admin/legal/terms_of_use/versions/%d/preview', $draft->getVersionNumber());
        $client->request('GET', $draftPreview);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Descargar PDF');

        $client->request('GET', sprintf('/admin/legal/terms_of_use/versions/%d/pdf', $draft->getVersionNumber()));
        self::assertResponseRedirects($draftPreview);

        $developerId = $developer->getId();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $developer = $this->entityManager->find(User::class, $developerId);
        self::assertInstanceOf(User::class, $developer);

        $privacy = $this->publishDocument(
            $developer,
            LegalDocumentType::PrivacyPolicy,
            'Política de prueba',
            'Acepto la política de prueba',
        );

        $crawler = $client->request('GET', '/admin/legal/accept');
        $form = $crawler->selectButton('Aceptar y continuar')->form();
        $accepted = $form['accepted'];
        self::assertInstanceOf(ChoiceFormField::class, $accepted);
        $accepted->tick();
        $client->submit($form);

        $previewUrl = sprintf('/admin/legal/privacy_policy/versions/%d/preview?lang=es', $privacy->getVersionNumber());
        $crawler = $client->request('GET', $previewUrl);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf(
            'a[href$="/versions/%d/pdf?lang=es"]',
            $privacy->getVersionNumber(),
        ));
        self::assertSelectorTextContains('body', 'Descargar PDF');

        $client->disableReboot();
        $pdfExporter = $this->createMock(LegalDocumentPdfExporter::class);
        $pdfExporter->expects($this->once())->method('export')->willReturn('%PDF-1.4 legal');
        static::getContainer()->set(LegalDocumentPdfExporter::class, $pdfExporter);

        $client->request('GET', sprintf('/admin/legal/privacy_policy/versions/%d/pdf?lang=es', $privacy->getVersionNumber()));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        $disposition = $client->getResponse()->headers->get('Content-Disposition');
        self::assertIsString($disposition);
        self::assertStringContainsString(sprintf('-v%d-es.pdf"', $privacy->getVersionNumber()), $disposition);
        self::assertSame('%PDF-1.4 legal', $client->getResponse()->getContent());
    }

    public function testIncompleteProfileSkipsTheLegalGate(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->publishPrivacyPolicy($developer);

        $incomplete = $this->createIncompleteUser();
        $client->loginUser($incomplete, 'main');
        $client->request('GET', '/admin/home');

        self::assertResponseRedirects('/admin/profile');
        $this->entityManager->remove($incomplete);
        $this->entityManager->flush();
    }

    private function publishPrivacyPolicy(User $actor): LegalDocumentVersion
    {
        return $this->publishDocument(
            $actor,
            LegalDocumentType::PrivacyPolicy,
            'Política de prueba',
            'Acepto la política de prueba',
        );
    }

    private function publishDocument(User $actor, LegalDocumentType $type, string $title, string $acceptanceLabel): LegalDocumentVersion
    {
        /** @var LegalDocumentPublisher $publisher */
        $publisher = static::getContainer()->get(LegalDocumentPublisher::class);
        $publisher->ensureDocuments();

        /** @var LegalDocumentRepository $documents */
        $documents = static::getContainer()->get(LegalDocumentRepository::class);
        $document = $documents->findOneByType($type);
        self::assertNotNull($document);

        /** @var GeneralSettingsRepository $settingsRepository */
        $settingsRepository = static::getContainer()->get(GeneralSettingsRepository::class);
        $languages = $settingsRepository->getOrCreateSingleton()->getEnabledLanguages();

        $draft = $publisher->startDraft($document, $actor);
        $versionId = $draft->getId();
        self::assertNotNull($versionId);
        $this->versionId = $versionId;
        $this->versionIds[] = $versionId;
        $payload = [];

        foreach ($languages as $language) {
            $payload[$language] = [
                'title' => $title,
                'contentHtml' => '<p>Contenido de prueba</p>',
                'acceptanceLabel' => $acceptanceLabel,
                'summary' => 'Versión de prueba',
            ];
        }

        $publisher->updateDraft($draft, $payload);
        $this->notificationTitle = $title . ' ' . $draft->getVersionNumber() . ' ' . uniqid('', true);
        $this->notificationTitles[] = $this->notificationTitle;
        $publisher->publish($draft, true, $this->notificationTitle, 'Debes aceptar el documento de prueba.', $actor);

        return $draft;
    }

    private function createIncompleteUser(): User
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $suffix = uniqid('', true);
        $user = new User()
            ->setName('Incomplete')
            ->setLastname('Legal')
            ->setEmail('legal-incomplete-' . $suffix . '@example.com')
            ->setNickname('legal-incomplete-' . $suffix)
            ->setCountryCode(57)
            ->setCellphone(substr(str_replace('.', '', $suffix), 0, 15))
            ->setApplicationRoles([UserRole::Operator])
            ->setStatus(UserStatus::UncompleteProfileInfo);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }
}
