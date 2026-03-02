# ER-диаграмма базы данных системы управления задачами

## Легенда диаграммы

```textline
[Сущность]                           {Атрибуты}
PK ○ ИмяПоля                         * Обязательное поле
FK ● ИмяПоля                         ? Необязательное поле
   ─ ИмяПоля                         # Индекс
```

## Основные сущности и связи

### 1. Пользователи и связанные сущности

```textline
[USERS]
PK ○ id
   ● username*
   ● email*
   ● roles*
   ● password*
   ● first_name?
   ● last_name?
   ● phone?
   ● position?
   ● department?
   ● timezone?
   ● locale?
   ● is_active*
   ● last_login_at?
   ● created_at*
   ● updated_at?
   # idx_users_email
   # idx_users_username
   # idx_users_active

         │
         │ 1
         │
         │ N
         ▼

[TASKS]                              [CATEGORIES]
PK ○ id                              PK ○ id
FK ● user_id*                        FK ● user_id*
FK ● category_id?                    ● name*
● title*                             ● color?
● description?                       ● icon?
● status*                            ● is_active*
● priority*                          ● created_at*
● due_date?                          # idx_categories_user
● start_date?
● completed_at?                      │
● estimated_hours?                   │ 1
● actual_hours?                      │
● parent_id?                         │ N
● created_at*                        ▼
● updated_at?
# idx_tasks_user                   [TASKS]
# idx_tasks_status                 (самореференс)
# idx_tasks_priority
# idx_tasks_due_date
# idx_tasks_category

         │
         │ N
         │
         │ N
         ▼

[TASK_TAG]                           [COMMENTS]
● task_id*                           PK ○ id
● tag_id*                            FK ● task_id*
# idx_task_tag_task                   FK ● user_id*
# idx_task_tag_tag                    FK ● parent_id?
                                     ● content*
                                     ● is_pinned?
                                     ● created_at*
                                     ● updated_at?
                                     # idx_comments_task
                                     # idx_comments_user
```

### 2. Система времени и повторений

```textline
[TASKS]
PK ○ id
   ...

         │
         │ 1
         │
         │ N
         ▼

[TASK_TIME_TRACKING]                 [TASK_RECURRENCES]
PK ○ id                              PK ○ id
FK ● task_id*                        FK ● task_id*
FK ● user_id*                        FK ● user_id*
● start_time*                        ● frequency*
● end_time?                          ● interval*
● duration*                          ● days_of_week?
● description?                       ● day_of_month?
● is_billable*                       ● month?
● created_at*                        ● start_date*
                                     ● end_date?
[TASK_TEMPLATES]                     ● next_run_date*
PK ○ id                              ● is_active*
FK ● user_id*                        ● created_at*
FK ● category_id?                    # idx_recurrences_next_run
● name*
● description?
● priority*
● estimated_hours?
● is_active*
● created_at*
● updated_at*
```

### 3. CRM-система

```textline
[USERS]
PK ○ id
   ...

         │
         │ 1
         │
         │ N
         ▼

[CLIENTS]                            [DEALS]
PK ○ id                              PK ○ id
● company_name*                      FK ● client_id*
● inn?                               FK ● manager_id* (→ USERS.id)
● kpp?                               ● title*
● contact_person?                    ● amount*
● phone?                             ● currency*
● email?                             ● status*
● address?                           ● probability*
● segment*                           ● expected_close_date?
● category*                          ● actual_close_date?
FK ● manager_id?                     ● created_at*
● notes?                             ● updated_at*
● created_at*                        # idx_deals_client
● updated_at?                        # idx_deals_manager
● last_contact_at?                   # idx_deals_status
# idx_clients_segment
# idx_clients_category
# idx_clients_manager

         │
         │ 1
         │
         │ N
         ▼

[CLIENT_INTERACTIONS]
PK ○ id
FK ● client_id*
FK ● user_id*
● interaction_type*
● subject*
● description?
● outcome?
● next_action?
● scheduled_at?
● completed_at?
● created_at*
```

### 4. Система уведомлений и настроек

```textline
[USERS]
PK ○ id
   ...

         │
         │ 1
         │
         │ N
         ▼

[NOTIFICATIONS]                      [NOTIFICATION_PREFERENCES]
PK ○ id                              PK ○ id
FK ● user_id*                        FK ● user_id*
● type*                              ● notification_type*
● title*                             ● channel*
● message*                           ● is_enabled*
● entity_type?                       ● frequency*
● entity_id?                         ● time_window_start?
● is_read*                           ● time_window_end?
● read_at?                           ● created_at*
● created_at*                        ● updated_at*
# idx_notifications_user
# idx_notifications_read
# idx_notifications_entity

         │
         │ 1
         │
         │ N
         ▼

[NOTIFICATION_TEMPLATES]
PK ○ id
● name*
● subject*
● content*
● type*
● is_active*
● created_at*
● updated_at*
```

### 5. Финансовая система

```textline
[USERS]
PK ○ id
   ...

         │
         │ 1
         │
         │ N
         ▼

[BUDGETS]                            [PRODUCTS]
PK ○ id                              PK ○ id
● name*                              ● name*
● total_amount*                      ● sku* (UNIQUE)
● spent_amount*                      ● description?
● start_date*                        ● category*
● end_date?                          ● price*
FK ● created_by*                     ● cost?
● status*                            ● unit?
● currency*                          ● is_active*
● created_at*                        ● created_at*
● updated_at*                        ● updated_at*
# idx_budgets_user                  # idx_products_category
# idx_budgets_status                # idx_products_active
# idx_budgets_dates

         │
         │ 1
         │
         │ N
         ▼

[DOCUMENTS]
PK ○ id
● title*
● content*
● document_type*
● status*
FK ● created_by*
● created_at*
● updated_at*
```

### 6. Система целей и привычек

```textline
[USERS]
PK ○ id
   ...

         │
         │ 1
         │
         │ N
         ▼

[GOALS]                              [HABITS]
PK ○ id                              PK ○ id
FK ● user_id*                        FK ● user_id*
● title*                             ● name*
● description?                       ● description?
● status*                            ● frequency*
● priority*                          ● target_count*
● start_date?                        ● is_active*
● end_date?                          ● created_at*
● progress*                          # idx_habits_user
● created_at*
● updated_at*
# idx_goals_user
# idx_goals_status

         │
         │ 1
         │
         │ N
         ▼

[GOAL_MILESTONES]
PK ○ id
FK ● goal_id*
● title*
● description?
● target_date*
● completed_at?
● is_completed*
● created_at*
```

### 7. Система знаний

```textline
[USERS]
PK ○ id
   ...

         │
         │ 1
         │
         │ N
         ▼

[KNOWLEDGE_BASE_CATEGORIES]          [KNOWLEDGE_BASE_ARTICLES]
PK ○ id                              PK ○ id
● name*                              FK ● category_id*
● description?                       FK ● author_id*
● slug*                              ● title*
● is_active*                         ● content*
● created_at*                        ● status*
● updated_at*                        ● views_count*
# idx_kb_categories_active           ● is_featured*
                                     ● published_at?
                                     ● created_at*
                                     ● updated_at*
                                     # idx_kb_articles_category
                                     # idx_kb_articles_status
                                     # idx_kb_articles_featured
```

### 8. Системные сущности

```textline
[WEBHOOKS]                           [ACTIVITY_LOGS]
PK ○ id                              PK ○ id
FK ● user_id*                        FK ● user_id*
● name*                              ● entity_type*
● url*                               ● entity_id*
● event_types*                       ● action*
● is_active*                         ● details?
● secret?                            ● ip_address?
● created_at*                        ● user_agent?
● updated_at*                        ● created_at*
# idx_webhooks_user                 # idx_activity_logs_user
# idx_webhooks_active               # idx_activity_logs_entity

[USER_PREFERENCES]                   [USER_DASHBOARD_LAYOUTS]
PK ○ id                              PK ○ id
FK ● user_id*                        FK ● user_id*
● preference_key*                    ● layout_config*
● preference_value*                  ● is_default*
● created_at*                        ● created_at*
● updated_at*                        ● updated_at*
# idx_preferences_user

[USER_DEVICES]                       [USER_INTEGRATIONS]
PK ○ id                              PK ○ id
FK ● user_id*                        FK ● user_id*
● device_token*                      ● service_name*
● device_type*                       ● access_token*
● is_active*                         ● refresh_token?
● last_used_at*                      ● expires_at?
● created_at*                        ● is_active*
# idx_devices_user                  ● created_at*
# idx_devices_active                ● updated_at*
```

## Связи многие-ко-многим

### Система тегов

```textline
[TASKS] ○───N:M───○ [TAGS]
             │
             ▼
       [TASK_TAG]
       ● task_id* (FK)
       ● tag_id* (FK)
       # idx_task_tag_task
       # idx_task_tag_tag
```

### Система зависимостей задач

```textline
[TASKS] ○───N:M───○ [TASKS]
             │
             ▼
    [TASK_DEPENDENCIES]
       ● task_id* (FK)
       ● dependent_task_id* (FK)
       ● dependency_type*
       ● created_at*
       # idx_dependencies_task
       # idx_dependencies_dependent
```

## Цветовая кодировка связей

- **Сплошная линия**: Обязательные связи (`NOT NULL`)
- **Пунктирная линия**: Необязательные связи (`NULLABLE`)
- **Жирная линия**: Слабые сущности (зависят от родительской)
- **Стрелки**: Направление связи и кратность

## Условные обозначения кратности

- **1:1** - Одна к одной
- **1:N** - Одна ко многим  
- **N:1** - Многие к одной
- **N:M** - Многие ко многим

## Индексы для оптимизации

### Критические индексы по частоте использования:

1. **Пользовательские запросы**: idx_users_email, idx_users_username
2. **Задачи**: idx_tasks_user, idx_tasks_status, idx_tasks_due_date
3. **Комментарии**: idx_comments_task, idx_comments_user
4. **Уведомления**: idx_notifications_user, idx_notifications_read
5. **CRM**: idx_clients_manager, idx_deals_client

### Составные индексы:

- idx_tasks_user_status (user_id, status)
- idx_tasks_category_status (category_id, status)
- idx_comments_task_created (task_id, created_at)
- idx_notifications_user_read (user_id, is_read)
