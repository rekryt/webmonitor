# WebMonitor Prometheus Exporter

Экспортер метрик мониторинга сетевых служб для Prometheus. С поддержкой уведомлений через Telegram.

## Установка
```bash
git clone https://github.com/rekryt/webmonitor.git
cd webmonitor
cp .env.example .env
docker compose build
```

Настройте env-параметры:

| имя                    | описание                             |
|------------------------|--------------------------------------|
| `SYS_AUTH_LOGIN`       | логин для http авторизации           |
| `SYS_AUTH_PASSWORD`    | пароль                               |
| `SYS_METRICS_PREFIX`   | префикс имени метрик                 |
| `TELEGRAM_BOT_TOKEN`   | токен Telegram бота                  |
| `TELEGRAM_BOT_CHAT_ID` | id чата\пользователя для уведомлений |

Создайте конфигурации в папке config в формате `portal.json`:
```json
[
    {
        "name": "response from google.com",
        "type": "http",
        "params": {
            "url": "https://google.com",
            "timeout": 30
        }
    },
    {
        "name": "ping from google.com",
        "type": "ping",
        "params": {
            "host": "google.com",
            "timeout": 30
        }
    },
    {
        "name": "tcp connect to google.com",
        "type": "tcp",
        "params": {
            "host": "google.com",
            "port": 443,
            "timeout": 30
        }
    }
]
```

Доступные типы (`type`) проверок в json конфигурациях порталов:

| значение | описание                                            |
|----------|-----------------------------------------------------|
| `http`   | проверка что код ответа 200 и тело ответа не пустое |
| `ping`   | проверка через icmp ping                            |
| `tcp`    | проверка на установку tcp соединения                |

Запустите приложение
```bash
docker compose up -d
```
На соответствующем порту (по умолчанию `8080`) экспортер метрик будет доступен.
![image](https://github.com/user-attachments/assets/51cfcec0-3a27-4b36-bc3c-32ab91b81493)

## Конфигурация prometheus
```yaml
scrape_configs:
  - job_name: 'webmonitor'
    scrape_interval: 30s
    basic_auth:
      username: 'loginFromEnvFile'
      password: 'passwordFromEnvFile'
    static_configs:
      - targets: ['yourHostOrIpAddress:8080']
```
