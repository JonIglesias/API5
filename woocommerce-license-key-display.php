<?php
/**
 * Plugin Name: WooCommerce License Key Display
 * Plugin URI: https://github.com/JonIglesias/API5
 * Description: Muestra la clave de licencia generada por la API en los pedidos de WooCommerce y en los emails
 * Version: 1.0.0
 * Author: Jon Iglesias
 * Author URI: https://github.com/JonIglesias
 * Text Domain: wc-license-display
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package WC_License_Display
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mostrar la license key en la página de detalle del pedido (Mi cuenta)
 *
 * @param WC_Order $order Objeto del pedido
 */
function wc_license_display_show_in_order_details($order) {
    // Obtener el ID del pedido
    $order_id = $order->get_id();

    // Obtener la license key del meta data
    $license_key = $order->get_meta('_license_key');

    // Si no existe, no mostrar nada
    if (empty($license_key)) {
        return;
    }

    // HTML con estilos
    ?>
    <section class="woocommerce-license-key" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border: 2px solid #28a745; border-radius: 8px;">
        <h2 style="margin-top: 0; color: #28a745; font-size: 20px; display: flex; align-items: center; gap: 10px;">
            <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                <path d="M3.5 11.5a3.5 3.5 0 1 1 3.163-5H14L15.5 8 14 9.5l-1-1-1 1-1-1-1 1-1-1-1 1H6.663a3.5 3.5 0 0 1-3.163 2zM2.5 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
            </svg>
            <?php esc_html_e('Tu Clave de Licencia', 'wc-license-display'); ?>
        </h2>
        <div style="background: white; padding: 15px; border-radius: 5px; margin-top: 15px;">
            <p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">
                <?php esc_html_e('Guarda esta clave en un lugar seguro. La necesitarás para activar tu producto:', 'wc-license-display'); ?>
            </p>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <code id="license-key-value" style="font-size: 18px; font-weight: bold; color: #333; background: #f0f0f0; padding: 12px 16px; border-radius: 4px; letter-spacing: 1px; flex: 1; min-width: 200px; font-family: 'Courier New', monospace;">
                    <?php echo esc_html($license_key); ?>
                </code>
                <button type="button" onclick="copyLicenseKey()" style="background: #28a745; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: background 0.3s;">
                    📋 <?php esc_html_e('Copiar', 'wc-license-display'); ?>
                </button>
            </div>
            <p id="copy-feedback" style="margin: 10px 0 0 0; color: #28a745; font-size: 13px; font-weight: bold; display: none;">
                ✓ <?php esc_html_e('¡Clave copiada al portapapeles!', 'wc-license-display'); ?>
            </p>
        </div>
    </section>

    <script>
    function copyLicenseKey() {
        const licenseKey = document.getElementById('license-key-value').textContent.trim();
        const feedback = document.getElementById('copy-feedback');

        // Copiar al portapapeles
        navigator.clipboard.writeText(licenseKey).then(function() {
            // Mostrar feedback
            feedback.style.display = 'block';

            // Ocultar después de 3 segundos
            setTimeout(function() {
                feedback.style.display = 'none';
            }, 3000);
        }).catch(function(err) {
            alert('<?php esc_html_e('Error al copiar. Por favor, copia manualmente.', 'wc-license-display'); ?>');
        });
    }
    </script>
    <?php
}
add_action('woocommerce_order_details_after_order_table', 'wc_license_display_show_in_order_details', 10, 1);


/**
 * Mostrar la license key en los emails de WooCommerce
 *
 * @param WC_Order $order Objeto del pedido
 * @param bool $sent_to_admin Si se envía al admin
 * @param bool $plain_text Si es texto plano
 * @param WC_Email $email Objeto del email
 */
function wc_license_display_show_in_email($order, $sent_to_admin, $plain_text, $email) {
    // Solo mostrar en emails al cliente (no al admin)
    if ($sent_to_admin) {
        return;
    }

    // Obtener la license key
    $license_key = $order->get_meta('_license_key');

    // Si no existe, no mostrar nada
    if (empty($license_key)) {
        return;
    }

    // Diferentes formatos para HTML y texto plano
    if ($plain_text) {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo strtoupper(__('Tu Clave de Licencia', 'wc-license-display')) . "\n";
        echo str_repeat('=', 50) . "\n\n";
        echo __('Guarda esta clave en un lugar seguro:', 'wc-license-display') . "\n\n";
        echo $license_key . "\n";
        echo str_repeat('=', 50) . "\n\n";
    } else {
        ?>
        <div style="margin: 30px 0; padding: 20px; background: #f8f9fa; border: 2px solid #28a745; border-radius: 8px; font-family: Arial, sans-serif;">
            <h2 style="margin: 0 0 15px 0; color: #28a745; font-size: 20px;">
                🔑 <?php esc_html_e('Tu Clave de Licencia', 'wc-license-display'); ?>
            </h2>
            <p style="margin: 0 0 15px 0; color: #666; font-size: 14px; line-height: 1.5;">
                <?php esc_html_e('Guarda esta clave en un lugar seguro. La necesitarás para activar tu producto:', 'wc-license-display'); ?>
            </p>
            <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                <code style="font-size: 18px; font-weight: bold; color: #333; letter-spacing: 2px; font-family: 'Courier New', monospace;">
                    <?php echo esc_html($license_key); ?>
                </code>
            </div>
            <p style="margin: 15px 0 0 0; color: #999; font-size: 12px; line-height: 1.5;">
                <?php esc_html_e('También puedes encontrar tu clave de licencia en cualquier momento accediendo a los detalles de tu pedido en tu cuenta.', 'wc-license-display'); ?>
            </p>
        </div>
        <?php
    }
}
add_action('woocommerce_email_after_order_table', 'wc_license_display_show_in_email', 10, 4);


/**
 * Mostrar la license key en el admin del pedido (panel de administración)
 *
 * @param WC_Order $order Objeto del pedido
 */
function wc_license_display_show_in_admin($order) {
    $license_key = $order->get_meta('_license_key');

    if (empty($license_key)) {
        return;
    }
    ?>
    <div class="order-license-key" style="margin: 20px 0; padding: 15px; background: #f0f9ff; border-left: 4px solid #0073aa;">
        <p style="margin: 0 0 10px 0; font-weight: bold; color: #0073aa;">
            🔑 <?php esc_html_e('Clave de Licencia:', 'wc-license-display'); ?>
        </p>
        <code style="font-size: 14px; background: white; padding: 8px 12px; display: inline-block; border-radius: 3px; font-family: 'Courier New', monospace;">
            <?php echo esc_html($license_key); ?>
        </code>
    </div>
    <?php
}
add_action('woocommerce_admin_order_data_after_billing_address', 'wc_license_display_show_in_admin', 10, 1);


/**
 * Añadir columna de License Key en la lista de pedidos del admin
 *
 * @param array $columns Columnas existentes
 * @return array Columnas modificadas
 */
function wc_license_display_add_admin_column($columns) {
    $new_columns = array();

    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;

        // Añadir después de la columna de estado
        if ($key === 'order_status') {
            $new_columns['license_key'] = __('License Key', 'wc-license-display');
        }
    }

    return $new_columns;
}
add_filter('manage_edit-shop_order_columns', 'wc_license_display_add_admin_column', 20);


/**
 * Mostrar el contenido de la columna License Key
 *
 * @param string $column Nombre de la columna
 */
function wc_license_display_admin_column_content($column) {
    global $post;

    if ($column === 'license_key') {
        $order = wc_get_order($post->ID);
        $license_key = $order->get_meta('_license_key');

        if (!empty($license_key)) {
            echo '<code style="font-size: 11px; background: #f0f0f0; padding: 3px 6px; border-radius: 3px;">' . esc_html($license_key) . '</code>';
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}
add_action('manage_shop_order_posts_custom_column', 'wc_license_display_admin_column_content', 10, 1);
