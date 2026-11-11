# assets/

Frontend на Webpack Encore + Stimulus. Сборка — yarn, не npm.

## Что внутри

```
assets/
├── app.js                    entrypoint: SCSS, Bootstrap, jQuery, datepicker
├── bootstrap.js              запуск Stimulus и автозагрузка контроллеров
├── controllers/              Stimulus-контроллеры (registry, act, info, month)
├── controllers.json          сторонние контроллеры (ux-chartjs)
└── styles/app.scss           общие стили
```

Рядом в корне: `webpack.config.js` (Encore: entry app, SCSS, versioning),
`package.json` (зависимости и скрипты), `yarn.lock`.

## Сборка

```bash
yarn install --frozen-lockfile
yarn build          # production
yarn dev            # dev
yarn watch          # dev + watch
```

- `package.json` ссылается на `file:vendor/symfony/...`, поэтому сначала должен быть
  установлен `vendor/` (в Docker — из образа).
- `public/build/` — generated, не коммитить. В Docker это анонимный volume, после смены
  зависимостей нужна пересборка образа.
- В шаблонах подключается entry `app` через `encore_entry_link_tags('app')` и
  `encore_entry_script_tags('app')`.

## Правила

- Новый Stimulus-контроллер — в `assets/controllers/`, имя файла `<name>_controller.js`.
- Сторонний контроллер — через `controllers.json`, а не ручная регистрация.
- Не добавлять глобальные обработчики в `app.js`, если можно сделать контроллер.
- Стили — в `assets/styles/app.scss` или рядом с контроллером; не инлайнить в Twig без
  необходимости.
