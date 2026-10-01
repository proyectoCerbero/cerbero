# Configuración del módulo de Ayuda

El código no guarda contraseñas ni claves dentro del repositorio. Copiá `.env.example` como `.env` y completá únicamente tu archivo local. En XAMPP el backend carga ese archivo automáticamente cada vez que se usa el módulo de Contacto.

## Chatbot con IA

Definí `OPENAI_API_KEY` con una clave válida. `OPENAI_MODEL` puede mantenerse como `gpt-5-mini` o cambiarse por otro modelo compatible con la API Responses.

Si la clave no está disponible o el servicio no responde, Cerbero usa automáticamente respuestas generales locales y continúa derivando las preguntas específicas al correo de contacto.

## Envío de correos

El destinatario ya está fijado en `cerberoSIGERU@gmail.com`. Para enviar mediante Gmail configurá:

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=cerberoSIGERU@gmail.com
SMTP_PASSWORD=CONTRASENA_DE_APLICACION
CERBERO_MAIL_FROM=cerberoSIGERU@gmail.com
```

`SMTP_PASSWORD` debe ser una contraseña de aplicación de Google, no la contraseña normal de la cuenta. No subas el archivo `.env` a GitHub.

En XAMPP guardá el archivo en la carpeta principal del proyecto, por ejemplo:

```text
C:\xampp\htdocs\proyecto\.env
```

Después abrí `http://localhost/proyecto/frontend/ayuda.html`, enviá una consulta de prueba y revisá la bandeja de `cerberoSIGERU@gmail.com` (incluido Spam). No compartas ni subas el archivo `.env` a GitHub.
