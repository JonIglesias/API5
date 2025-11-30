<?php
/**
 * Plugin Name: WooCommerce License Key Display & Custom My Account
 * Plugin URI: https://github.com/JonIglesias/API5
 * Description: Muestra la clave de licencia generada por la API en los pedidos de WooCommerce, en los emails, y proporciona shortcodes para crear páginas Mi Cuenta personalizadas
 * Version: 2.0.1
 * Author: Jon Iglesias
 * Author URI: https://github.com/JonIglesias
 * Text Domain: wc-license-display
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package WC_License_Display
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Declarar compatibilidad con características de WooCommerce
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('orders_cache', __FILE__, true);
    }
});

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
add_filter('manage_woocommerce_page_wc-orders_columns', 'wc_license_display_add_admin_column', 20);


/**
 * Mostrar el contenido de la columna License Key (para pedidos tradicionales)
 *
 * @param string $column Nombre de la columna
 */
function wc_license_display_admin_column_content($column) {
    global $post;

    if ($column === 'license_key' && isset($post->ID)) {
        $order = wc_get_order($post->ID);
        if ($order) {
            $license_key = $order->get_meta('_license_key');

            if (!empty($license_key)) {
                echo '<code style="font-size: 11px; background: #f0f0f0; padding: 3px 6px; border-radius: 3px;">' . esc_html($license_key) . '</code>';
            } else {
                echo '<span style="color: #999;">—</span>';
            }
        }
    }
}
add_action('manage_shop_order_posts_custom_column', 'wc_license_display_admin_column_content', 10, 1);


/**
 * Mostrar el contenido de la columna License Key (para HPOS)
 *
 * @param string $column Nombre de la columna
 * @param WC_Order $order Objeto del pedido
 */
function wc_license_display_admin_column_content_hpos($column, $order) {
    if ($column === 'license_key') {
        $license_key = $order->get_meta('_license_key');

        if (!empty($license_key)) {
            echo '<code style="font-size: 11px; background: #f0f0f0; padding: 3px 6px; border-radius: 3px;">' . esc_html($license_key) . '</code>';
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}
add_action('manage_woocommerce_page_wc-orders_custom_column', 'wc_license_display_admin_column_content_hpos', 10, 2);


// ============================================================================
// SHORTCODES PERSONALIZADOS PARA MI CUENTA
// ============================================================================

/**
 * Shortcode: [wc_account_details]
 * Muestra los datos de la cuenta del cliente (nombre, email, etc.)
 */
function wc_shortcode_account_details($atts) {
    // Verificar que WooCommerce esté activo
    if (!function_exists('WC') || !class_exists('WC_Customer')) {
        return '<p style="color: red;">Error: WooCommerce no está activo.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p>' . __('Debes iniciar sesión para ver esta información.', 'wc-license-display') . '</p>';
    }

    try {
        $user = wp_get_current_user();
        $customer = new WC_Customer($user->ID);

        ob_start();
        ?>
        <div class="wc-account-details-shortcode">
            <h2><?php _e('Mis Datos', 'wc-license-display'); ?></h2>
            <div class="account-info-grid">
                <div class="info-item">
                    <span class="label"><?php _e('Nombre:', 'wc-license-display'); ?></span>
                    <span class="value"><?php echo esc_html($customer->get_first_name()); ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><?php _e('Apellidos:', 'wc-license-display'); ?></span>
                    <span class="value"><?php echo esc_html($customer->get_last_name()); ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><?php _e('Email:', 'wc-license-display'); ?></span>
                    <span class="value"><?php echo esc_html($customer->get_email()); ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><?php _e('Nombre de usuario:', 'wc-license-display'); ?></span>
                    <span class="value"><?php echo esc_html($user->user_login); ?></span>
                </div>
            </div>
            <a href="<?php echo esc_url(wc_get_endpoint_url('edit-account', '', wc_get_page_permalink('myaccount'))); ?>" class="button">
                <?php _e('Editar mis datos', 'wc-license-display'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    } catch (Exception $e) {
        return '<p style="color: red;">Error al cargar datos de cuenta: ' . esc_html($e->getMessage()) . '</p>';
    }
}
add_shortcode('wc_account_details', 'wc_shortcode_account_details');


/**
 * Shortcode: [wc_addresses]
 * Muestra las direcciones de facturación y envío
 */
function wc_shortcode_addresses($atts) {
    // Verificar que WooCommerce esté activo
    if (!function_exists('WC') || !class_exists('WC_Customer')) {
        return '<p style="color: red;">Error: WooCommerce no está activo.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p>' . __('Debes iniciar sesión para ver esta información.', 'wc-license-display') . '</p>';
    }

    try {
        $customer = new WC_Customer(get_current_user_id());

    ob_start();
    ?>
    <div class="wc-addresses-shortcode">
        <h2><?php _e('Mis Direcciones', 'wc-license-display'); ?></h2>

        <div class="addresses-grid">
            <!-- Dirección de facturación -->
            <div class="address-block billing-address">
                <h3><?php _e('Dirección de Facturación', 'wc-license-display'); ?></h3>
                <?php
                $billing = array(
                    'first_name' => $customer->get_billing_first_name(),
                    'last_name' => $customer->get_billing_last_name(),
                    'company' => $customer->get_billing_company(),
                    'address_1' => $customer->get_billing_address_1(),
                    'address_2' => $customer->get_billing_address_2(),
                    'city' => $customer->get_billing_city(),
                    'state' => $customer->get_billing_state(),
                    'postcode' => $customer->get_billing_postcode(),
                    'country' => $customer->get_billing_country(),
                );

                if (array_filter($billing)) {
                    echo '<address>';
                    echo esc_html($billing['first_name'] . ' ' . $billing['last_name']) . '<br>';
                    if ($billing['company']) echo esc_html($billing['company']) . '<br>';
                    echo esc_html($billing['address_1']) . '<br>';
                    if ($billing['address_2']) echo esc_html($billing['address_2']) . '<br>';
                    echo esc_html($billing['city']) . ', ' . esc_html($billing['state']) . ' ' . esc_html($billing['postcode']) . '<br>';
                    echo esc_html(WC()->countries->countries[$billing['country']] ?? $billing['country']);
                    echo '</address>';
                } else {
                    echo '<p class="no-address">' . __('No has configurado una dirección de facturación.', 'wc-license-display') . '</p>';
                }
                ?>
                <a href="<?php echo esc_url(wc_get_endpoint_url('edit-address', 'billing', wc_get_page_permalink('myaccount'))); ?>" class="button">
                    <?php _e('Editar', 'wc-license-display'); ?>
                </a>
            </div>

            <!-- Dirección de envío -->
            <div class="address-block shipping-address">
                <h3><?php _e('Dirección de Envío', 'wc-license-display'); ?></h3>
                <?php
                $shipping = array(
                    'first_name' => $customer->get_shipping_first_name(),
                    'last_name' => $customer->get_shipping_last_name(),
                    'company' => $customer->get_shipping_company(),
                    'address_1' => $customer->get_shipping_address_1(),
                    'address_2' => $customer->get_shipping_address_2(),
                    'city' => $customer->get_shipping_city(),
                    'state' => $customer->get_shipping_state(),
                    'postcode' => $customer->get_shipping_postcode(),
                    'country' => $customer->get_shipping_country(),
                );

                if (array_filter($shipping)) {
                    echo '<address>';
                    echo esc_html($shipping['first_name'] . ' ' . $shipping['last_name']) . '<br>';
                    if ($shipping['company']) echo esc_html($shipping['company']) . '<br>';
                    echo esc_html($shipping['address_1']) . '<br>';
                    if ($shipping['address_2']) echo esc_html($shipping['address_2']) . '<br>';
                    echo esc_html($shipping['city']) . ', ' . esc_html($shipping['state']) . ' ' . esc_html($shipping['postcode']) . '<br>';
                    echo esc_html(WC()->countries->countries[$shipping['country']] ?? $shipping['country']);
                    echo '</address>';
                } else {
                    echo '<p class="no-address">' . __('No has configurado una dirección de envío.', 'wc-license-display') . '</p>';
                }
                ?>
                <a href="<?php echo esc_url(wc_get_endpoint_url('edit-address', 'shipping', wc_get_page_permalink('myaccount'))); ?>" class="button">
                    <?php _e('Editar', 'wc-license-display'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
        return ob_get_clean();
    } catch (Exception $e) {
        return '<p style="color: red;">Error al cargar direcciones: ' . esc_html($e->getMessage()) . '</p>';
    }
}
add_shortcode('wc_addresses', 'wc_shortcode_addresses');


/**
 * Shortcode: [wc_payment_methods]
 * Muestra los métodos de pago guardados
 */
function wc_shortcode_payment_methods($atts) {
    // Verificar que WooCommerce esté activo
    if (!function_exists('WC') || !function_exists('wc_get_customer_saved_methods_list')) {
        return '<p style="color: red;">Error: WooCommerce no está activo.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p>' . __('Debes iniciar sesión para ver esta información.', 'wc-license-display') . '</p>';
    }

    try {
        ob_start();
        ?>
        <div class="wc-payment-methods-shortcode">
            <h2><?php _e('Mis Métodos de Pago', 'wc-license-display'); ?></h2>
            <?php
            $saved_methods = wc_get_customer_saved_methods_list(get_current_user_id());

        if (!empty($saved_methods)) {
            foreach ($saved_methods as $type => $methods) {
                foreach ($methods as $method) {
                    ?>
                    <div class="payment-method-item">
                        <span class="method-type"><?php echo esc_html($method['method']['brand'] ?? $type); ?></span>
                        <span class="method-details"><?php echo esc_html($method['method']['last4'] ?? ''); ?></span>
                        <span class="method-expires"><?php echo esc_html($method['expires'] ?? ''); ?></span>
                        <?php if ($method['is_default']) : ?>
                            <span class="default-badge"><?php _e('Predeterminado', 'wc-license-display'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php
                }
            }
        } else {
            echo '<p>' . __('No tienes métodos de pago guardados.', 'wc-license-display') . '</p>';
        }
        ?>
        <a href="<?php echo esc_url(wc_get_endpoint_url('payment-methods', '', wc_get_page_permalink('myaccount'))); ?>" class="button">
            <?php _e('Gestionar métodos de pago', 'wc-license-display'); ?>
        </a>
    </div>
    <?php
        return ob_get_clean();
    } catch (Exception $e) {
        return '<p style="color: red;">Error al cargar métodos de pago: ' . esc_html($e->getMessage()) . '</p>';
    }
}
add_shortcode('wc_payment_methods', 'wc_shortcode_payment_methods');


/**
 * Shortcode: [wc_orders_with_subscriptions]
 * Muestra la lista de pedidos con sus suscripciones y claves de licencia
 *
 * Atributos:
 * - limit: número de pedidos a mostrar (default: 10)
 * - status: estado de los pedidos (default: any)
 */
function wc_shortcode_orders_with_subscriptions($atts) {
    // Verificar que WooCommerce esté activo
    if (!function_exists('WC') || !function_exists('wc_get_orders')) {
        return '<p style="color: red;">Error: WooCommerce no está activo.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p>' . __('Debes iniciar sesión para ver tus pedidos.', 'wc-license-display') . '</p>';
    }

    try {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'status' => 'any'
        ), $atts);

        $customer_orders = wc_get_orders(array(
            'customer' => get_current_user_id(),
            'limit' => $atts['limit'],
            'status' => $atts['status'],
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        if (empty($customer_orders)) {
            return '<p>' . __('No tienes pedidos todavía.', 'wc-license-display') . '</p>';
        }

        ob_start();
    ?>
    <div class="wc-orders-subscriptions-shortcode">
        <h2><?php _e('Mis Pedidos y Suscripciones', 'wc-license-display'); ?></h2>

        <?php foreach ($customer_orders as $order) : ?>
            <div class="order-block" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                <!-- DATOS DEL PEDIDO -->
                <div class="order-header">
                    <div class="order-number">
                        <strong><?php _e('Pedido #', 'wc-license-display'); ?><?php echo esc_html($order->get_order_number()); ?></strong>
                    </div>
                    <div class="order-date">
                        <?php echo esc_html($order->get_date_created()->date_i18n('d/m/Y')); ?>
                    </div>
                    <div class="order-status">
                        <span class="status-badge status-<?php echo esc_attr($order->get_status()); ?>">
                            <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                        </span>
                    </div>
                </div>

                <div class="order-details">
                    <!-- Productos del pedido -->
                    <div class="order-products">
                        <h4><?php _e('Productos', 'wc-license-display'); ?></h4>
                        <?php foreach ($order->get_items() as $item_id => $item) :
                            $product = $item->get_product();
                            ?>
                            <div class="product-item">
                                <span class="product-name"><?php echo esc_html($item->get_name()); ?></span>
                                <span class="product-id">ID: <?php echo esc_html($product ? $product->get_id() : 'N/A'); ?></span>
                                <span class="product-quantity">x<?php echo esc_html($item->get_quantity()); ?></span>
                                <span class="product-total"><?php echo $order->get_formatted_line_subtotal($item); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Total del pedido -->
                    <div class="order-total">
                        <strong><?php _e('Total:', 'wc-license-display'); ?></strong>
                        <span class="total-amount"><?php echo $order->get_formatted_order_total(); ?></span>
                    </div>

                    <!-- Clave de licencia -->
                    <?php
                    $license_key = $order->get_meta('_license_key');
                    if (!empty($license_key)) :
                    ?>
                        <div class="order-license-key">
                            <h4>🔑 <?php _e('Clave de Licencia', 'wc-license-display'); ?></h4>
                            <div class="license-key-display">
                                <code class="license-code"><?php echo esc_html($license_key); ?></code>
                                <button type="button" class="copy-license-btn" onclick="copyToClipboard('<?php echo esc_js($license_key); ?>', this)">
                                    📋 <?php _e('Copiar', 'wc-license-display'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SUSCRIPCIONES ASOCIADAS -->
                <?php
                // Buscar suscripciones relacionadas con este pedido
                if (function_exists('wcs_get_subscriptions_for_order')) {
                    $subscriptions = wcs_get_subscriptions_for_order($order->get_id(), array('order_type' => 'any'));

                    if (!empty($subscriptions)) :
                        foreach ($subscriptions as $subscription) :
                ?>
                        <div class="subscription-block">
                            <div class="subscription-header">
                                <h4>📋 <?php _e('Suscripción #', 'wc-license-display'); ?><?php echo esc_html($subscription->get_order_number()); ?></h4>
                                <span class="subscription-status status-<?php echo esc_attr($subscription->get_status()); ?>">
                                    <?php echo esc_html(wcs_get_subscription_status_name($subscription->get_status())); ?>
                                </span>
                            </div>

                            <div class="subscription-details">
                                <div class="subscription-info-grid">
                                    <div class="info-item">
                                        <span class="label"><?php _e('Fecha de inicio:', 'wc-license-display'); ?></span>
                                        <span class="value"><?php echo esc_html($subscription->get_date('start') ? $subscription->get_date_to_display('start') : '-'); ?></span>
                                    </div>

                                    <?php if ($subscription->get_date('next_payment')) : ?>
                                    <div class="info-item">
                                        <span class="label"><?php _e('Próximo pago:', 'wc-license-display'); ?></span>
                                        <span class="value next-payment"><?php echo esc_html($subscription->get_date_to_display('next_payment')); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($subscription->get_date('end')) : ?>
                                    <div class="info-item">
                                        <span class="label"><?php _e('Fecha de finalización:', 'wc-license-display'); ?></span>
                                        <span class="value"><?php echo esc_html($subscription->get_date_to_display('end')); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="info-item">
                                        <span class="label"><?php _e('Importe:', 'wc-license-display'); ?></span>
                                        <span class="value"><?php echo $subscription->get_formatted_order_total(); ?></span>
                                    </div>
                                </div>

                                <!-- Acciones de suscripción -->
                                <div class="subscription-actions">
                                    <?php if ($subscription->can_be_updated_to('cancelled')) : ?>
                                        <a href="<?php echo esc_url($subscription->get_cancel_endpoint()); ?>"
                                           class="button cancel-subscription"
                                           onclick="return confirm('<?php echo esc_js(__('¿Estás seguro de que deseas cancelar esta suscripción?', 'wc-license-display')); ?>')">
                                            <?php _e('Cancelar suscripción', 'wc-license-display'); ?>
                                        </a>
                                    <?php endif; ?>

                                    <a href="<?php echo esc_url($subscription->get_view_order_url()); ?>" class="button view-subscription">
                                        <?php _e('Ver detalles', 'wc-license-display'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                <?php
                        endforeach;
                    endif;
                }
                ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
    function copyToClipboard(text, button) {
        navigator.clipboard.writeText(text).then(function() {
            const originalText = button.textContent;
            button.textContent = '✓ <?php echo esc_js(__('¡Copiado!', 'wc-license-display')); ?>';
            button.style.background = '#28a745';

            setTimeout(function() {
                button.textContent = originalText;
                button.style.background = '';
            }, 2000);
        }).catch(function(err) {
            alert('<?php echo esc_js(__('Error al copiar. Por favor, copia manualmente.', 'wc-license-display')); ?>');
        });
    }
    </script>
    <?php
        return ob_get_clean();
    } catch (Exception $e) {
        return '<p style="color: red;">Error al cargar pedidos: ' . esc_html($e->getMessage()) . '</p>';
    }
}
add_shortcode('wc_orders_with_subscriptions', 'wc_shortcode_orders_with_subscriptions');


/**
 * Añadir estilos CSS básicos para los shortcodes
 */
function wc_shortcodes_enqueue_styles() {
    if (is_singular() || is_page()) {
        ?>
        <style>
        /* Estilos generales */
        .wc-account-details-shortcode,
        .wc-addresses-shortcode,
        .wc-payment-methods-shortcode,
        .wc-orders-subscriptions-shortcode {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Account Details */
        .account-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-item .label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
        }

        .info-item .value {
            font-size: 16px;
            color: #333;
        }

        /* Addresses */
        .addresses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 20px 0;
        }

        .address-block {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #f9f9f9;
        }

        .address-block h3 {
            margin-top: 0;
            color: #333;
            font-size: 18px;
        }

        .address-block address {
            font-style: normal;
            line-height: 1.6;
            margin: 15px 0;
        }

        /* Payment Methods */
        .payment-method-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin: 10px 0;
            background: #f9f9f9;
        }

        .default-badge {
            background: #28a745;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Orders and Subscriptions */
        .order-block {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 30px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-number {
            font-size: 18px;
        }

        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.status-completed,
        .status-badge.status-processing { background: #d4edda; color: #155724; }
        .status-badge.status-pending { background: #fff3cd; color: #856404; }
        .status-badge.status-cancelled { background: #f8d7da; color: #721c24; }
        .status-badge.status-active { background: #d1ecf1; color: #0c5460; }

        .order-details {
            padding: 20px;
        }

        .order-products {
            margin-bottom: 20px;
        }

        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            gap: 10px;
            flex-wrap: wrap;
        }

        .product-name { flex: 1; font-weight: 500; }
        .product-id { color: #666; font-size: 13px; }

        .order-total {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            padding: 15px 0;
            border-top: 2px solid #e0e0e0;
            margin-top: 10px;
            font-size: 18px;
        }

        .order-license-key {
            background: #f0f9ff;
            border: 2px solid #3498db;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .order-license-key h4 {
            margin-top: 0;
            color: #2c3e50;
        }

        .license-key-display {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .license-code {
            background: white;
            padding: 12px 16px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 1px;
            flex: 1;
            min-width: 200px;
        }

        .copy-license-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .copy-license-btn:hover {
            background: #2980b9;
        }

        .subscription-block {
            background: #f8f9fa;
            border-top: 2px solid #e0e0e0;
            padding: 20px;
            margin-top: 20px;
        }

        .subscription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .subscription-header h4 {
            margin: 0;
            color: #2c3e50;
        }

        .subscription-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }

        .next-payment {
            color: #28a745;
            font-weight: 600;
        }

        .subscription-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
        }

        .button:hover {
            background: #2980b9;
        }

        .button.cancel-subscription {
            background: #dc3545;
        }

        .button.cancel-subscription:hover {
            background: #c82333;
        }

        .button.view-subscription {
            background: #6c757d;
        }

        .button.view-subscription:hover {
            background: #5a6268;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .order-header,
            .subscription-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .product-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .subscription-info-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
}
add_action('wp_head', 'wc_shortcodes_enqueue_styles');
