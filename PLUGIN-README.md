# WooCommerce License Key Display Plugin

Plugin de WordPress/WooCommerce para mostrar las claves de licencia generadas por la API5 en los pedidos.

## 📋 Características

- ✅ Muestra la clave de licencia en la página de detalle del pedido (Mi cuenta)
- ✅ Incluye la clave en todos los emails de WooCommerce (confirmación, completado, etc.)
- ✅ Botón de "Copiar al portapapeles" en la página del pedido
- ✅ Vista de la clave en el panel de administración de pedidos
- ✅ Columna de "License Key" en la lista de pedidos del admin
- ✅ Diseño responsive y profesional
- ✅ Compatible con emails HTML y texto plano
- ✅ Multiidioma ready (Text Domain: wc-license-display)

## 🚀 Instalación

### Opción 1: Instalación manual (recomendada)

1. Copia el archivo `woocommerce-license-key-display.php` a la carpeta de plugins de WordPress:
   ```bash
   /wp-content/plugins/woocommerce-license-key-display/woocommerce-license-key-display.php
   ```

2. Accede al panel de administración de WordPress
3. Ve a **Plugins** → **Plugins instalados**
4. Busca "WooCommerce License Key Display"
5. Haz clic en **Activar**

### Opción 2: Subir mediante el panel de WordPress

1. Comprime el archivo `woocommerce-license-key-display.php` en un ZIP
2. En WordPress, ve a **Plugins** → **Añadir nuevo** → **Subir plugin**
3. Selecciona el archivo ZIP y haz clic en **Instalar ahora**
4. Activa el plugin

## 📸 Qué verá el cliente

### En la página del pedido (Mi cuenta)

```
┌─────────────────────────────────────────────────┐
│ 🔑 Tu Clave de Licencia                         │
│                                                  │
│ Guarda esta clave en un lugar seguro:           │
│                                                  │
│ ┌──────────────────────┐  ┌──────────┐         │
│ │ BASI-2025-A1B2C3D4   │  │ 📋 Copiar │         │
│ └──────────────────────┘  └──────────┘         │
│                                                  │
└─────────────────────────────────────────────────┘
```

### En los emails

La clave aparece automáticamente después de la tabla de productos del pedido, con un diseño destacado en verde.

### En el panel de admin

- En la lista de pedidos: columna "License Key" con la clave
- En el detalle del pedido: sección especial mostrando la clave

## 🔧 Configuración

**No requiere configuración**. El plugin funciona automáticamente una vez activado.

El plugin busca automáticamente el meta field `_license_key` en los pedidos (el mismo que genera la API5).

## 🔍 Requisitos

- WordPress 5.8 o superior
- WooCommerce 5.0 o superior
- PHP 7.4 o superior

## 🎨 Personalización

Si quieres personalizar los estilos o textos, puedes editar directamente el archivo del plugin:

- **Línea 34-64**: Estilos de la página del pedido
- **Línea 100-138**: Estilos de los emails
- **Textos**: Busca las funciones `esc_html_e()` y `__()` para cambiar los textos

## 📝 Hooks disponibles

El plugin utiliza estos hooks de WooCommerce:

- `woocommerce_order_details_after_order_table` - Página del pedido
- `woocommerce_email_after_order_table` - Emails
- `woocommerce_admin_order_data_after_billing_address` - Admin del pedido
- `manage_edit-shop_order_columns` - Columna en lista de pedidos
- `manage_shop_order_posts_custom_column` - Contenido de la columna

## 🐛 Troubleshooting

### La clave no aparece en los pedidos

1. Verifica que el plugin esté activado
2. Comprueba que la API5 esté enviando correctamente el meta field `_license_key`
3. Revisa los logs de webhook en `/logs/webhook.log`
4. Haz un pedido de prueba nuevo

### La clave no aparece en los emails

1. Algunos plugins de email personalizados pueden interferir
2. Verifica que estés usando las plantillas estándar de WooCommerce
3. Prueba desactivando otros plugins de email temporalmente

## 📄 Licencia

Este plugin es de código abierto y puede ser modificado según tus necesidades.

## 👨‍💻 Autor

Jon Iglesias - [GitHub](https://github.com/JonIglesias)

## 🔄 Versión

**1.0.0** - Versión inicial
- Muestra la clave en página de pedido
- Muestra la clave en emails
- Muestra la clave en admin
- Botón de copiar al portapapeles
