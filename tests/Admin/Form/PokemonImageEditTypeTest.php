<?php

declare(strict_types=1);

namespace App\Tests\Admin\Form;

use App\Admin\Form\PokemonImageEditType;
use App\Entity\PokemonImage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

use function str_repeat;

#[CoversClass(PokemonImageEditType::class)]
#[Group('integration')]
final class PokemonImageEditTypeTest extends KernelTestCase
{
    public function testEmptyDescriptionIsValid(): void
    {
        $form = $this->createForm();
        $form->submit(['description' => '']);

        self::assertTrue($form->isValid());
        self::assertSame('', $form->get('description')->getData());
    }

    public function testDescriptionIsAcceptedAtMaxLength(): void
    {
        $form = $this->createForm();
        $form->submit(['description' => str_repeat('a', PokemonImage::DESCRIPTION_MAX_LENGTH)]);

        self::assertTrue($form->isValid());
    }

    public function testDescriptionRejectsValuesAboveMaxLength(): void
    {
        $form = $this->createForm();
        $form->submit(['description' => str_repeat('a', PokemonImage::DESCRIPTION_MAX_LENGTH + 1)]);

        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, $form->get('description')->getErrors(true)->count());
    }

    public function testDescriptionExposesMaxlengthAttribute(): void
    {
        $view = $this->createForm()->createView();

        self::assertSame(PokemonImage::DESCRIPTION_MAX_LENGTH, $view['description']->vars['attr']['maxlength']);
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createForm(): FormInterface
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);

        return $formFactory->create(PokemonImageEditType::class);
    }
}
