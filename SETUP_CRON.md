# Configuración del Cron para Auto-Sync

## ¿Qué hace el cron?

El cron ejecuta automáticamente cada 5 minutos:

1. **Sincroniza licencias desde WooCommerce a la API**
   - Obtiene pedidos nuevos/modificados de las últimas 2 horas
   - Crea o actualiza licencias en la base de datos local

2. **Envía license_keys pendientes a WooCommerce**
   - Busca licencias que aún no se han sincronizado a WooCommerce
   - Añade el `_license_key` como metadata del pedido
   - Envía un email automático al cliente con la licencia

3. **Limpieza de logs antiguos**
   - Elimina logs de más de 30 días de la base de datos
   - Trunca archivos de log muy grandes

## Configurar el cron en el servidor

### Paso 1: Obtener rutas absolutas

Necesitas saber la ruta completa a:
- PHP ejecutable: `which php` (normalmente `/usr/bin/php`)
- Directorio API5: `pwd` desde la carpeta API5

### Paso 2: Editar crontab

```bash
crontab -e
```

### Paso 3: Añadir la línea del cron

**Para servidor de producción (bocetosmarketing.com):**

```bash
# Auto-sync WooCommerce cada 5 minutos
*/5 * * * * /usr/bin/php /home/bocetosm/public_html/api_claude_5/cron/auto-sync.php recent >> /home/bocetosm/public_html/api_claude_5/logs/cron.log 2>&1
```

**Ajusta las rutas según tu servidor:**
- `/usr/bin/php` → Ruta al ejecutable PHP (usa `which php` para encontrarlo)
- `/home/bocetosm/public_html/api_claude_5` → Ruta completa a tu carpeta API5
- `>> logs/cron.log` → Guarda el output en el archivo de log
- `2>&1` → Captura tanto stdout como stderr

### Paso 4: Verificar que funciona

Espera 5-10 minutos y luego revisa:

```bash
# Ver el log del cron
tail -f /ruta/a/API5/logs/cron.log

# Ver el estado del último sync
cat /ruta/a/API5/logs/cron_status.json

# Ver si se están sincronizando licencias
tail -f /ruta/a/API5/logs/sync.log
```

## Ejecutar manualmente para probar

Antes de configurar el cron, puedes probar manualmente:

```bash
# Sync reciente (últimas 2 horas) - RECOMENDADO para cron
php /ruta/a/API5/cron/auto-sync.php recent

# Sync completo (todos los pedidos) - Solo para sync inicial
php /ruta/a/API5/cron/auto-sync.php full
```

## Verificar estado en el panel de admin

Una vez configurado, puedes ver el estado en:
- Panel de Admin → Dashboard
- Verás estadísticas de sincronización y última ejecución del cron

## Tipos de sync

### Sync Recent (para cron cada 5 min)
- Sincroniza pedidos de las últimas 2 horas
- Rápido y eficiente
- **RECOMENDADO para cron automático**

### Sync Full (para sync inicial o manual)
- Sincroniza TODOS los pedidos
- Puede tardar varios minutos
- Usar solo para sync inicial o cuando hay problemas

## Solución de problemas

### El cron no se ejecuta
1. Verificar que la ruta al PHP es correcta: `which php`
2. Verificar permisos de ejecución: `chmod +x cron/auto-sync.php`
3. Revisar logs del sistema: `/var/log/cron` o `/var/log/syslog`

### No se sincronizan las licencias a WooCommerce
1. Verificar que hay licencias pendientes en la base de datos:
   ```sql
   SELECT * FROM api_licenses WHERE license_key_synced_to_woo = 0
   ```
2. Verificar que las licencias tienen `woo_subscription_id` o `last_order_id` válido
3. Revisar logs: `tail -f logs/sync.log`

### El email no llega al cliente
- El email se envía automáticamente vía WooCommerce Customer Notes
- Verificar que WooCommerce tiene emails configurados correctamente
- Revisar logs de WooCommerce en WordPress admin

## Estructura de logs

- `logs/cron.log` - Output directo del cron (stdout/stderr)
- `logs/sync.log` - Log detallado de sincronizaciones
- `logs/cron_status.json` - Estado de la última ejecución
- `logs/api.log` - Peticiones a la API
- `logs/webhook.log` - Webhooks recibidos

## Limpieza automática

El cron limpia automáticamente:
- Logs de base de datos: >30 días
- Archivos de log: >7 días o >10MB
- Archivos .bak: >7 días

Para limpiar TODO manualmente:
- Ir al panel de Admin → Sync → Botón "Limpiar todos los logs"
