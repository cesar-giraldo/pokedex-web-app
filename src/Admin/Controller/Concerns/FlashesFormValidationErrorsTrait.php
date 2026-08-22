<?php

declare(strict_types=1);

namespace App\Admin\Controller\Concerns;

use Symfony\Component\Form\FormInterface;

use function in_array;
use function sprintf;

trait FlashesFormValidationErrorsTrait
{
    /**
     * @param FormInterface<mixed> $form
     */
    private function flashFormValidationErrors(FormInterface $form, string $intro = 'Revisa los campos marcados.'): void
    {
        $errorMessages = $this->collectFormErrorMessages($form);

        if ([] === $errorMessages) {
            $this->addFlash('error', $intro);

            return;
        }

        $this->addFlash(
            'error',
            sprintf('%s: %s', rtrim($intro, '.'), implode(', ', $errorMessages)),
        );
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return list<string>
     */
    private function collectFormErrorMessages(FormInterface $form): array
    {
        $messages = [];

        foreach ($form->getErrors(true) as $error) {
            $message = trim($error->getMessage());

            if ('' !== $message && !in_array($message, $messages, true)) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
