<?php

namespace App\Controller\Traits;

/**
 * Trait for standardized flash messages
 */
trait FlashMessageTrait
{
    protected function flashSuccess(string $message): void
    {
        $this->addFlash('success', $message);
    }

    protected function flashError(string $message): void
    {
        $this->addFlash('error', $message);
    }

    protected function flashWarning(string $message): void
    {
        $this->addFlash('warning', $message);
    }

    protected function flashInfo(string $message): void
    {
        $this->addFlash('info', $message);
    }

    // Common messages
    protected function flashCreated(string $entity = 'Запись'): void
    {
        $this->flashSuccess("{$entity} успешно создана");
    }

    protected function flashUpdated(string $entity = 'Запись'): void
    {
        $this->flashSuccess("{$entity} успешно обновлена");
    }

    protected function flashDeleted(string $entity = 'Запись'): void
    {
        $this->flashSuccess("{$entity} успешно удалена");
    }

    protected function flashNotFound(string $entity = 'Запись'): void
    {
        $this->flashError("{$entity} не найдена");
    }

    protected function flashAccessDenied(): void
    {
        $this->flashError('Доступ запрещен');
    }

    protected function flashInvalidData(): void
    {
        $this->flashError('Неверные данные');
    }
}
