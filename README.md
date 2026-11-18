# Trámites más claros, personas más autónomas

Prototipo de orientación para trámites de vivienda en Huancayo, dirigido especialmente a personas adultas mayores. Aplicación Laravel 12, PHP 8.2+, MySQL y Chart.js.

## Módulos

- `/`: guía pública y asistente con el tríptico digital de seis paneles, cuatro casos originales, requisitos, pasos y listas de preparación.
- `/admin`: panel privado con visitas, sesiones, casos, comportamiento, embudo y exportación CSV. Requiere un usuario con `is_admin=true`.
- `/login`: inicio de sesión administrativo. El registro público está deshabilitado.

## Instalación local

1. Copia `.env.example` a `.env` y configura `APP_KEY`, `APP_URL`, `APP_TIMEZONE=America/Lima` y la conexión MySQL.
2. Ejecuta `composer install` y `npm ci`.
3. Ejecuta `php artisan key:generate` si la clave está vacía.
4. Ejecuta `php artisan migrate --force` y `php artisan db:seed --class=PrototypeCaseSeeder`.
5. Inicia la aplicación con `php artisan serve`.

Las migraciones crean sesiones anónimas, eventos analíticos, casos y ajustes administrativos. No se generan visitas ni datos de analítica ficticios; el dashboard permanece en cero hasta que existan visitas reales.

### Datos de demostración del dashboard

Para una presentación, después de desplegar las migraciones y cargar los cuatro casos, ejecuta `php artisan db:seed --class=DemoAnalyticsSeeder`. Crea 58 sesiones sintéticas sin nombres ni DNI, distribuidas en los últimos 30 días. El dashboard las suma a las métricas del periodo y las identifica como “Demo (simulado)”; las exportaciones también señalan su origen. Ejecutar el seeder de nuevo reemplaza únicamente las sesiones demo existentes. Para retirarlas después de la presentación, usa `php artisan tinker --execute="App\Models\VisitorSession::where('is_demo', true)->delete();"`.

## Crear el primer administrador

En `.env`, configura `ADMIN_NAME`, `ADMIN_EMAIL` y una `ADMIN_PASSWORD` de al menos 12 caracteres. Después ejecuta:

```sh
php artisan db:seed --class=AdminUserSeeder
```

La contraseña se guarda con el hash nativo de Laravel. No se incluye ninguna credencial por defecto. Borra `ADMIN_PASSWORD` del entorno después de inicializar la cuenta y conserva la contraseña en un gestor seguro. Para dar de alta administradores adicionales, usa un proceso de mantenimiento autenticado o Artisan; no hay formulario público de registro.

## Eventos y métricas

`POST /analytics/events` acepta eventos con UUID deduplicable y una sesión aleatoria almacenada en `sessionStorage`. El servidor no recibe nombre, DNI, teléfono, dirección IP para almacenamiento analítico ni texto libre del chatbot. Guarda tipo de evento, caso, ruta, dispositivo, navegador, marca de tiempo, duración aproximada y metadatos limitados. Los agentes automatizados comunes se descartan y el endpoint tiene rate limit.

Eventos: `page_view`, `session_start`, `case_view`, `case_select`, `requirements_view`, `steps_view`, `checklist_start`, `checklist_progress`, `checklist_complete`, `help_click`, `navigation_click` y `page_exit`. Una sesión única es una sesión anónima del navegador/pestaña, no una persona verificada. La salida se mide cuando el navegador permite `pagehide` o `visibilitychange`; cierres abruptos pueden no reportar duración.

El embudo cuenta sesiones únicas por etapa. Los porcentajes expresan paso entre etapas consecutivas y no comprensión del trámite. Las consultas de uso son indicios de interacción, no una medida de satisfacción o autonomía. Para evaluar comprensión, se recomienda añadir una encuesta voluntaria y anónima.

Filtros del panel: hoy, 7/30/90 días, mes actual/anterior, fechas personalizadas, caso y dispositivo. Los datos horarios usan `APP_TIMEZONE=America/Lima`. La serie de visitas se agrupa por día, semana o mes. El CSV respeta filtros y omite datos personales.

## Publicar en cPanel

1. Usa PHP 8.2 o posterior y activa extensiones `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo` y `bcmath`.
2. Crea una base y usuario MySQL desde cPanel y asigna los permisos necesarios.
3. Sube el proyecto fuera del directorio público cuando sea posible. Configura el document root del dominio/subdominio a la carpeta `public`.
4. Sube `.env` por separado, con `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://tu-dominio`, `APP_TIMEZONE=America/Lima` y credenciales MySQL del hosting. Ejecuta `php artisan key:generate` solo si el entorno no tiene una clave estable.
5. Instala dependencias en desarrollo, compila `npm run build` y sube los archivos generados en `public/build`; Chart.js está incluido localmente en `public/js/chart.umd.js`.
6. Ejecuta `php artisan migrate --force` y `php artisan db:seed --class=PrototypeCaseSeeder` desde terminal cPanel/SSH. Configura variables `ADMIN_*` temporalmente y ejecuta `php artisan db:seed --class=AdminUserSeeder` para crear la cuenta.
7. Configura `MAIL_MAILER=smtp`, servidor, puerto, usuario, contraseña y cifrado SMTP del hosting para que la recuperación de contraseña pueda entregar el enlace al administrador.
8. Asegura permisos de escritura para `storage` y `bootstrap/cache`; habilita HTTPS; luego ejecuta `php artisan config:cache`, `php artisan route:cache` y `php artisan view:cache`.
9. No publiques `.env`, `vendor`, claves ni credenciales en el directorio público. No ejecutes `migrate:fresh` en producción.

Si el hosting no proporciona SSH/terminal, prepara `vendor` y los recursos compilados localmente; las migraciones y el seed inicial deben ejecutarse mediante una terminal segura o un proceso temporal protegido y retirado inmediatamente.

## Tablas principales

- `users`: cuentas administrativas y bandera `is_admin`.
- `visitor_sessions`: clave aleatoria de sesión, dispositivo, navegador y tiempos.
- `analytics_events`: eventos deduplicables, caso relacionado, página, duración y metadatos JSON.
- `prototype_cases`: los cuatro casos y su información de orientación.
- `admin_settings`: ajustes preparados para futuras opciones del panel.

Las antiguas tablas `guia_visitantes` y `consultas_tramite` pertenecen al prototipo previo. Sus endpoints ya no están registrados y esta versión no escribe en ellas. Se conservan para evitar borrar información previa automáticamente; revisa las obligaciones de retención antes de retirarlas.

## Pruebas

Configura en `phpunit.xml` o en el entorno un `DB_DATABASE` de pruebas separado (nunca producción) y ejecuta:

```sh
php artisan test
```

Las pruebas cubren acceso público sin PII, escritura anónima y deduplicación de eventos, protección del dashboard y exportación CSV.
