# templates/

Twig-шаблоны админки. Сгруппированы по конвейерам и контроллерам.

## Карта

```
templates/
├── base.html.twig            базовый layout (encore app)
├── backend.html.twig         layout админки
├── registry_command/         провайдерские реестры: список, issues, logs
├── registry_command_main/    главная провайдерских реестров
├── registry_command/search/  поиск и инструменты
├── registry_config/          настройки провайдеров
├── email_process/            реестры мерчантам
├── email_config/             настройки мерчантов
├── balance_process/          процессы балансов
├── balance_config/           настройки балансов
├── act/                      акты
├── month_registry/           месячные реестры
├── transaction/              транзакции
├── info/                     информация по транзакциям
├── find/                     поиск
├── two_registry/             сравнение двух реестров
├── security/                 логин и ошибки доступа
└── error/                    общие ошибки
```

## Правила

- Страницы админки наследуют `backend.html.twig`, общие — `base.html.twig`.
- Имя папки соответствует контроллеру; новые страницы клади в папку своего раздела.
- Не дублировать layout: общие блоки — в `backend.html.twig`.
- Ссылки на команды/статусы не хардкодить, если есть enum/репозиторий.
- JS-поведение — через Stimulus-контроллеры из `assets/controllers/`, а не инлайн-скрипты.
- После изменения шаблонов проверь страницу в браузере (http://localhost:8991) и логи.
