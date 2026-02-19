# Стандарты кода проекта

## 📋 Обзор

Этот проект использует автоматизированные инструменты для поддержания консистентности кода.

## 🛠️ Инструменты

### EditorConfig

Файл `.editorconfig` обеспечивает единообразие стиля кода в разных редакторах.

**Основные правила:**
- Кодировка: `UTF-8`
- Концы строк: `LF` (Unix)
- Отступы: `4 пробела` для PHP, `2 пробела` для YAML/JSON
- Максимальная длина строки: `120 символов`
- Конечная новая строка: `да`
- Удаление пробелов в конце строк: `да`

**Установка плагина для редактора:**
- **VS Code**: [EditorConfig for VS Code](https://marketplace.visualstudio.com/items?itemName=EditorConfig.EditorConfig)
- **PhpStorm**: Встроено (Settings → Editor → Code Style → EditorConfig)
- **Sublime Text**: [EditorConfig пакет](https://packagecontrol.io/packages/EditorConfig)

### PHP CS Fixer

Автоматическое форматирование кода по стандарту PSR-12.

**Установка:**
```bash
composer install
```

**Использование:**
```bash
# Проверка (dry-run)
composer cs
# или
php vendor/bin/php-cs-fixer fix --dry-run --diff

# Автоматическое исправление
composer cs:fix
# или
php vendor/bin/php-cs-fixer fix
```

**Конфигурация:** `.php-cs-fixer.dist.php`

**Основные правила:**
- PSR-12 coding standard
- Короткий синтаксис массивов `[]`
- Одинарные кавычки для строк
- Сортировка импортов по алфавиту
- Разделение методов пустой строкой

### PHPStan

Статический анализ кода для поиска ошибок.

**Установка:**
```bash
composer install
```

**Использование:**
```bash
# Запуск анализа
composer phpstan
# или
php vendor/bin/phpstan analyse --memory-limit=1G

# Проверка конкретного файла
php vendor/bin/phpstan analyse src/Controller/TaskController.php

# С генерацией baseline (для существующего проекта)
php vendor/bin/phpstan analyse --generate-baseline
```

**Конфигурация:** `phpstan.neon`
**Уровень:** 5 (рекомендуется для начала, можно повысить до 8)

### PHPUnit

Запуск тестов.

```bash
# Все тесты
composer test

# С покрытием
composer test:coverage

# Конкретный тест
php bin/phpunit tests/Controller/TaskControllerTest.php
```

## 📜 Правила кода

### PHP

#### Отступы и пробелы
```php
// ✅ Правильно
class TaskService
{
    public function create(TaskDTO $dto): Task
    {
        if ($dto->title === null) {
            throw new InvalidArgumentException('Title is required');
        }
        
        return new Task();
    }
}

// ❌ Неправильно (табы, нет пробелов)
class TaskService{
	public function create(TaskDTO $dto):Task{
		if($dto->title===null){
			throw new InvalidArgumentException('Title is required');
		}
		return new Task();
	}
}
```

#### Импорты
```php
// ✅ Правильно (отсортированы по алфавиту)
use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

// ❌ Неправильно (не отсортированы)
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
```

#### Массивы
```php
// ✅ Правильно (короткий синтаксис, запятая в конце)
$tasks = [
    'task1',
    'task2',
    'task3',
];

// ❌ Неправильно
$tasks = array('task1', 'task2', 'task3');
$tasks = [
    'task1',
    'task2',
    'task3'  // нет запятой
];
```

#### Строки
```php
// ✅ Правильно (одинарные кавычки)
$title = 'Task title';
$message = "Hello, {$user->getName()}";  // интерполяция

// ❌ Неправильно
$title = "Task title";  // двойные кавычки без интерполяции
```

#### Типы
```php
// ✅ Правильно (объявление строгих типов)
declare(strict_types=1);

namespace App\Service;

class TaskService
{
    public function getTask(int $id): ?Task
    {
        return $this->repository->find($id);
    }
}
```

#### Атрибуты (PHP 8+)
```php
// ✅ Правильно
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'tasks')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
}
```

#### Контроль потока
```php
// ✅ Правильно (без Yoda-стиля)
if ($status === 'completed') {
    return true;
}

// ✅ Правильно (упрощённый return)
public function isActive(): bool
{
    return $this->active;
}

// ❌ Неправильно
if ('completed' === $status) {
    if ($this->active) {
        return true;
    } else {
        return false;
    }
}
```

### Twig

```twig
{# ✅ Правильно #}
{% set tasks = tasks|filter(task => task.completed) %}

{% for task in tasks %}
    <div class="task">
        <h3>{{ task.title }}</h3>
        <p>{{ task.description|striptags }}</p>
    </div>
{% endfor %}

{# ❌ Неправильно (нет пробелов) #}
{%for task in tasks%}
<div class="task">{{task.title}}</div>
{%endfor%}
```

### YAML

```yaml
# ✅ Правильно (2 пробела, отсортировано)
services:
    App\Service\TaskService:
        arguments:
            $entityManager: '@doctrine.orm.entity_manager'
            $repository: '@App\Repository\TaskRepository'

# ❌ Неправильно (табы, нет сортировки)
services:
	App\Service\TaskService:
		arguments:
			$repository: '@App\Repository\TaskRepository'
			$entityManager: '@doctrine.orm.entity_manager'
```

## 🔄 Git Hooks

### Pre-commit hook

Автоматически проверяет код перед коммитом.

**Установка:**
```bash
# Windows (Git Bash)
git config core.hooksPath .githooks

# Linux/Mac
git config core.hooksPath .githooks
```

**Что проверяет:**
1. PHP CS Fixer (стиль кода)
2. PHPStan (статический анализ)

**Пропуск проверки (не рекомендуется):**
```bash
git commit --no-verify
```

## 📊 Composer скрипты

```bash
# Проверка стиля кода
composer cs

# Исправление стиля кода
composer cs:fix

# Статический анализ
composer phpstan

# Запуск тестов
composer test

# Тесты с покрытием
composer test:coverage

# Все проверки (cs + phpstan + test)
composer check
```

## 🔧 Настройка Git Hooks

Для автоматической проверки кода перед коммитом:

```bash
# Windows (Git Bash)
git config core.hooksPath .githooks

# Linux/Mac
git config core.hooksPath .githooks
```

**Pre-commit hook проверяет:**
1. PHP CS Fixer (стиль кода)
2. PHPStan (статический анализ)

**Пропуск проверки (не рекомендуется):**
```bash
git commit --no-verify
```

## 🎯 Рекомендации

### Перед коммитом
1. Запустите `composer cs:fix` для форматирования
2. Запустите `composer phpstan` для проверки типов
3. Запустите `composer test` для проверки тестов

### В редакторе

#### VS Code
Установите расширения:
- EditorConfig for VS Code
- PHP CS Fixer
- PHP Intelephense
- Twig Language 2

#### PhpStorm
Включите в настройках:
- Settings → Editor → Code Style → PHP → PSR-12
- Settings → Editor → EditorConfig
- Settings → Languages & Frameworks → PHP → Quality Tools → PHPStan

#### Sublime Text
Установите пакеты:
- EditorConfig
- PHP CS-Fixer

## 📚 Полезные ссылки

- [PSR-12 Coding Style Guide](https://www.php-fig.org/psr/psr-12/)
- [Symfony Coding Standards](https://symfony.com/doc/current/contributing/code/standards.html)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [PHP CS Fixer Documentation](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)
