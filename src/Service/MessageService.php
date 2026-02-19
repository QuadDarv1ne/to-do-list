<?php

namespace App\Service;

/**
 * Централизованный сервис для сообщений об ошибках и успехе
 */
class MessageService
{
    // Общие сообщения
    public const SUCCESS_CREATED = 'Успешно создано';

    public const SUCCESS_UPDATED = 'Успешно обновлено';

    public const SUCCESS_DELETED = 'Успешно удалено';

    public const ERROR_NOT_FOUND = 'Не найдено';

    public const ERROR_ACCESS_DENIED = 'Доступ запрещен';

    public const ERROR_INVALID_DATA = 'Неверные данные';

    // Задачи
    public const TASK_CREATED = 'Задача успешно создана';

    public const TASK_UPDATED = 'Задача успешно обновлена';

    public const TASK_DELETED = 'Задача успешно удалена';

    public const TASK_NOT_FOUND = 'Задача не найдена';

    public const TASK_COMPLETED = 'Задача отмечена как выполненная';

    // Пользователи
    public const USER_CREATED = 'Пользователь успешно создан';

    public const USER_UPDATED = 'Пользователь успешно обновлен';

    public const USER_DELETED = 'Пользователь успешно удален';

    public const USER_NOT_FOUND = 'Пользователь не найден';

    public const USER_ACTIVATED = 'Пользователь активирован';

    public const USER_DEACTIVATED = 'Пользователь деактивирован';

    public const USER_UNLOCKED = 'Пользователь разблокирован';

    public const USER_CANNOT_DELETE_ADMIN = 'Нельзя удалить системного администратора';

    // Зависимости задач
    public const DEPENDENCY_CREATED = 'Зависимость создана';

    public const DEPENDENCY_DELETED = 'Зависимость удалена';

    public const DEPENDENCY_EXISTS = 'Зависимость уже существует';

    public const DEPENDENCY_CIRCULAR = 'Это создаст циклическую зависимость';

    public const DEPENDENCY_SELF = 'Нельзя создать зависимость на ту же задачу';

    public const DEPENDENCY_NOT_FOUND = 'Зависимость не найдена';

    public const DEPENDENCY_IDS_REQUIRED = 'Требуются ID зависимостей';

    // Шаблоны
    public const TEMPLATE_NOT_FOUND = 'Шаблон не найден';

    public const TEMPLATE_CREATED = 'Создано %d задач из шаблона "%s"';

    public const TEMPLATE_FILL_REQUIRED = 'Пожалуйста, заполните название шаблона и хотя бы одну задачу';

    // Уведомления
    public const NOTIFICATION_CREATED = 'Уведомление создано успешно!';

    public const NOTIFICATION_UPDATED = 'Уведомление обновлено успешно!';

    public const NOTIFICATION_DELETED = 'Уведомление удалено успешно!';

    // Даты
    public const DATE_INVALID_FORMAT = 'Неверный формат даты';

    public const DATE_START_INVALID = 'Неверный формат даты начала';

    public const DATE_END_INVALID = 'Неверный формат даты окончания';

    public const DATE_CREATED_AFTER_INVALID = 'Неверный формат даты "создано после"';

    /**
     * Получить сообщение с подстановкой параметров
     */
    public static function format(string $message, ...$args): string
    {
        return \sprintf($message, ...$args);
    }

    /**
     * JSON ответ с ошибкой
     */
    public static function errorResponse(string $message, int $code = 400): array
    {
        return ['error' => $message, 'code' => $code];
    }

    /**
     * JSON ответ с успехом
     */
    public static function successResponse(string $message, array $data = []): array
    {
        return array_merge(['success' => true, 'message' => $message], $data);
    }
}
