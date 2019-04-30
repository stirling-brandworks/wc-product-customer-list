<?php

/**
 * @package WC_Product_Customer_List
 * @version 2.8.4
 */
// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
    die;
}
class Wpcl_Api
{
    private static  $necessary_capability = 'edit_shop_orders' ;
    private static  $rest_namespace = 'wpcl/v2' ;
    private static  $rest_route_order_items = '/order-items/' ;
    // We're taking care of everything in the init() method
    public function __construct()
    {
    }
    
    public static function get_rest_url( $type = 'order-items' )
    {
        switch ( $type ) {
            case 'order-items':
            default:
                return self::$rest_namespace . self::$rest_route_order_items;
                break;
        }
    }
    
    public function init()
    {
        // Create a REST route
        add_action( 'rest_api_init', array( $this, 'add_rest_route' ) );
    }
    
    public function add_rest_route()
    {
        register_rest_route( self::$rest_namespace, self::$rest_route_order_items, array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'process_order_items_rest_call' ),
            'args'                => array(
            'product_id' => array(
            'validate_callback' => function ( $param, $request, $key ) {
            return is_numeric( $param );
        },
        ),
        ),
            'permission_callback' => function () {
            return is_user_logged_in() && current_user_can( self::$necessary_capability );
        },
        ) );
    }
    
    public function process_order_items_rest_call( WP_REST_Request $data )
    {
        if ( !is_user_logged_in() || !current_user_can( self::$necessary_capability ) || !rest_cookie_check_errors( null ) ) {
            return new WP_REST_Response( array(
                'message' => __( 'You do not have the necessary capabilities for this request', 'wc-product-customer-list' ),
            ), 401 );
        }
        $params = $data->get_params();
        $orders = ( !empty($params['orders']) ? json_decode( $params['orders'] ) : false );
        $product_id = ( !empty($params['product_id']) ? filter_var( $params['product_id'], FILTER_SANITIZE_NUMBER_INT ) : false );
        $need_columns = ( !empty($params['need_columns']) ? $params['need_columns'] : false );
        if ( empty($product_id) || empty($orders) ) {
            return new WP_REST_Response( array(
                'message' => __( 'Missing data', 'wc-product-customer-list' ),
            ), 401 );
        }
        $item_data = $this->get_order_item_information( $product_id, $orders, false );
        // there was a problem with the data. for example: refunded order
        if ( $item_data['success'] === false ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => $item_data['reason'],
            ), 200 );
        }
        
        if ( $need_columns ) {
            // includes columns and emails
            return new WP_REST_Response( array(
                'success' => true,
                'data'    => $item_data['data'],
                'columns' => $item_data['columns'],
            ), 200 );
        } else {
            return new WP_REST_Response( array(
                'success' => true,
                'data'    => $item_data['data'],
            ), 200 );
        }
    
    }
    
    public function get_order_item_information( $product_id, $orders, $split_rows )
    {
        $product = WC()->product_factory->get_product( $product_id );
        $fields = array(
            'wpcl_order_number'          => array(
            'default_value'      => 'yes',
            'column_pretty_name' => __( 'Order', 'wc-product-customer-list' ),
            'object'             => 'sale',
            'property'           => 'order_id',
            'method'             => false,
            'format'             => false,
        ),
            'wpcl_order_date'            => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Date', 'wc-product-customer-list' ),
            'object'             => 'order',
            'property'           => false,
            'method'             => 'get_date_created',
            'format'             => "date_format( %someplaceholder, 'Y-m-d' ')",
        ),
            'wpcl_billing_first_name'    => array(
            'default_value'      => 'yes',
            'column_pretty_name' => __( 'Billing First name', 'wc-product-customer-list' ),
            'object'             => 'order',
            'property'           => false,
            'method'             => 'get_billing_first_name',
            'format'             => false,
        ),
            'wpcl_billing_last_name'     => array(
            'default_value'      => 'yes',
            'column_pretty_name' => __( 'Billing Last name', 'wc-product-customer-list' ),
        ),
            'wpcl_billing_company'       => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Billing Company', 'wc-product-customer-list' ),
        ),
            'wpcl_billing_email'         => array(
            'default_value'      => 'yes',
            'column_pretty_name' => __( 'Billing E-mail', 'wc-product-customer-list' ),
        ),
            'wpcl_billing_phone'         => array(
            'default_value'      => 'yes',
            'column_pretty_name' => __( 'Billing Phone', 'wc-product-customer-list' ),
        ),
            'wpcl_billing_address_1'     => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Billing Address 1', 'wc-product-customer-list' ),
        ),
            'wpcl_billing_address_2'     => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Billing Address 2', 'wc-product-customer-list' ),
        ),
            'wpcl_billing_city'          => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Billing City', 'wc-product-customer-list' ),
        ),
            'wpcl_billing_state'         => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Billing State', 'wc-product-customer-list' ),
        ),
            'wpcl_billing_postalcode'    => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Billing Postal Code / Zip', 'wc-product-customer-list' ),
        ),
            'wpcl_billing_country'       => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Billing Country', 'wc-product-customer-list' ),
        ),
            'wpcl_shipping_first_name'   => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Shipping First name', 'wc-product-customer-list' ),
        ),
            'wpcl_shipping_last_name'    => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Shipping Last name', 'wc-product-customer-list' ),
        ),
            'wpcl_shipping_company'      => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Shipping Company', 'wc-product-customer-list' ),
        ),
            'wpcl_shipping_address_1'    => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Shipping Address 1', 'wc-product-customer-list' ),
        ),
            'wpcl_shipping_address_2'    => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Shipping Address 2', 'wc-product-customer-list' ),
        ),
            'wpcl_shipping_city'         => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Shipping City', 'wc-product-customer-list' ),
        ),
            'wpcl_shipping_state'        => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Shipping State', 'wc-product-customer-list' ),
        ),
            'wpcl_shipping_postalcode'   => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Shipping Postal Code / Zip', 'wc-product-customer-list' ),
        ),
            'wpcl_shipping_country'      => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Shipping Country', 'wc-product-customer-list' ),
        ),
            'wpcl_customer_message'      => array(
            'default_value'      => 'yes',
            'column_pretty_name' => __( 'Customer Message', 'wc-product-customer-list' ),
        ),
            'wpcl_customer_id'           => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Customer ID', 'wc-product-customer-list' ),
        ),
            'wpcl_customer_username'     => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Customer username', 'wc-product-customer-list' ),
        ),
            'wpcl_customer_display_name' => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Customer display name', 'wc-product-customer-list' ),
        ),
            'wpcl_order_status'          => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Order Status', 'wc-product-customer-list' ),
        ),
            'wpcl_order_payment'         => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Payment method', 'wc-product-customer-list' ),
        ),
            'wpcl_order_shipping'        => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Shipping method', 'wc-product-customer-list' ),
        ),
            'wpcl_order_coupon'          => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Coupons used', 'wc-product-customer-list' ),
        ),
            'wpcl_variations'            => array(
            'default_value'      => 'yes',
            'column_pretty_name' => __( 'Variation', 'wc-product-customer-list' ),
        ),
            'wpcl_order_total'           => array(
            'default_value'      => 'no',
            'column_pretty_name' => __( 'Order total', 'wc-product-customer-list' ),
        ),
            'wpcl_order_qty'             => array(
            'default_value'      => 'yes',
            'column_pretty_name' => __( 'Qty', 'wc-product-customer-list' ),
        ),
        );
        foreach ( $fields as $option_name => $option_values ) {
            if ( get_option( $option_name, $option_values['default_value'] ) == 'yes' ) {
                $columns[$option_name] = $option_values['column_pretty_name'];
            }
        }
        foreach ( $orders as $order ) {
            $order_id = $order->order_id;
            $item_id = $order->order_item_id;
            $order = wc_get_order( $order_id );
            $formatted_total = $order->get_formatted_order_total();
            // Get quantity
            $refunded_qty = 0;
            $items = $order->get_items();
            foreach ( $items as $item_id => $item ) {
                if ( $item['product_id'] == $product_id ) {
                    $refunded_qty += $order->get_qty_refunded_for_item( $item_id );
                }
            }
            // Only one product per line if rows are split
            try {
                $quantity = wc_get_order_item_meta( $item_id, '_qty', true );
            } catch ( Exception $ex ) {
                $quantity = 0;
            }
            $quantity += $refunded_qty;
            // Check for partially refunded orders
            if ( $quantity == 0 && get_option( 'wpcl_order_partial_refunds', 'no' ) == 'yes' ) {
                // Order has been partially refunded
                return array(
                    'success' => false,
                    'reason'  => 'refunded order',
                );
            }
            $current_row = array();
            $current_row['billing_email'] = $order->get_billing_email();
            $current_row['order'] = $order;
            $current_row['order_id'] = $order_id;
            $current_row['order'] = $order;
            $current_row['product'] = $product;
            $current_row['product_id'] = $product_id;
            $current_row['item_id'] = $item_id;
            if ( isset( $columns['wpcl_order_number'] ) ) {
                $current_row['wpcl_order_number'] = $order_id;
            }
            if ( isset( $columns['wpcl_order_date'] ) ) {
                $current_row['wpcl_order_date'] = date_format( $order->get_date_created(), 'Y-m-d' );
            }
            if ( isset( $columns['wpcl_billing_first_name'] ) ) {
                $current_row['wpcl_billing_first_name'] = $order->get_billing_first_name();
            }
            if ( isset( $columns['wpcl_billing_last_name'] ) ) {
                $current_row['wpcl_billing_last_name'] = $order->get_billing_last_name();
            }
            if ( isset( $columns['wpcl_billing_company'] ) ) {
                $current_row['wpcl_billing_company'] = $order->get_billing_company();
            }
            if ( isset( $columns['wpcl_billing_email'] ) ) {
                $current_row['wpcl_billing_email'] = $order->get_billing_email();
            }
            if ( isset( $columns['wpcl_billing_phone'] ) ) {
                $current_row['wpcl_billing_phone'] = $order->get_billing_phone();
            }
            if ( isset( $columns['wpcl_billing_address_1'] ) ) {
                $current_row['wpcl_billing_address_1'] = $order->get_billing_address_1();
            }
            if ( isset( $columns['wpcl_billing_address_2'] ) ) {
                $current_row['wpcl_billing_address_2'] = $order->get_billing_address_2();
            }
            if ( isset( $columns['wpcl_billing_city'] ) ) {
                $current_row['wpcl_billing_city'] = $order->get_billing_city();
            }
            if ( isset( $columns['wpcl_billing_state'] ) ) {
                $current_row['wpcl_billing_state'] = $order->get_billing_state();
            }
            if ( isset( $columns['wpcl_billing_postalcode'] ) ) {
                $current_row['wpcl_billing_postalcode'] = $order->get_billing_postcode();
            }
            if ( isset( $columns['wpcl_billing_country'] ) ) {
                $current_row['wpcl_billing_country'] = $order->get_billing_country();
            }
            if ( isset( $columns['wpcl_shipping_first_name'] ) ) {
                $current_row['wpcl_shipping_first_name'] = $order->get_shipping_first_name();
            }
            if ( isset( $columns['wpcl_shipping_last_name'] ) ) {
                $current_row['wpcl_shipping_last_name'] = $order->get_shipping_last_name();
            }
            if ( isset( $columns['wpcl_shipping_company'] ) ) {
                $current_row['wpcl_shipping_company'] = $order->get_shipping_company();
            }
            if ( isset( $columns['wpcl_shipping_address_1'] ) ) {
                $current_row['wpcl_shipping_address_1'] = $order->get_shipping_address_1();
            }
            if ( isset( $columns['wpcl_shipping_address_2'] ) ) {
                $current_row['wpcl_shipping_address_2'] = $order->get_shipping_address_2();
            }
            if ( isset( $columns['wpcl_shipping_city'] ) ) {
                $current_row['wpcl_shipping_city'] = $order->get_shipping_city();
            }
            if ( isset( $columns['wpcl_shipping_state'] ) ) {
                $current_row['wpcl_shipping_state'] = $order->get_shipping_state();
            }
            if ( isset( $columns['wpcl_shipping_postalcode'] ) ) {
                $current_row['wpcl_shipping_postalcode'] = $order->get_shipping_postcode();
            }
            if ( isset( $columns['wpcl_shipping_country'] ) ) {
                $current_row['wpcl_shipping_country'] = $order->get_shipping_country();
            }
            if ( isset( $columns['wpcl_customer_message'] ) ) {
                $current_row['wpcl_customer_message'] = $order->get_customer_note();
            }
            $customer_id = $order->get_customer_id();
            $customer_info = ( !empty($customer_id) ? get_userdata( $customer_id ) : '' );
            $customer_username = ( !empty($customer_info) ? $customer_info->user_login : '' );
            $customer_userlogin = ( !empty($customer_info) ? get_admin_url() . 'user-edit.php?user_id=' . $customer_id : '' );
            $customer_displayname = ( !empty($customer_info) ? $customer_info->display_name : '' );
            if ( isset( $columns['wpcl_customer_login'] ) ) {
                $current_row['wpcl_customer_login'] = $customer_userlogin;
            }
            if ( isset( $columns['wpcl_customer_id'] ) ) {
                $current_row['wpcl_customer_id'] = $customer_id;
            }
            if ( isset( $columns['wpcl_customer_username'] ) ) {
                $current_row['wpcl_customer_username'] = $customer_username;
            }
            if ( isset( $columns['wpcl_customer_display_name'] ) ) {
                $current_row['wpcl_customer_display_name'] = $customer_displayname;
            }
            if ( isset( $columns['wpcl_order_status'] ) ) {
                $current_row['wpcl_order_status'] = wc_get_order_status_name( $order->get_status() );
            }
            if ( isset( $columns['wpcl_order_payment'] ) ) {
                $current_row['wpcl_order_payment'] = $order->get_payment_method_title();
            }
            if ( isset( $columns['wpcl_order_shipping'] ) ) {
                $current_row['wpcl_order_shipping'] = $order->get_shipping_method();
            }
            if ( isset( $columns['wpcl_order_coupon'] ) ) {
                $current_row['wpcl_order_coupon'] = implode( ', ', $order->get_used_coupons() );
            }
            
            if ( isset( $columns['wpcl_variations'] ) ) {
                $current_row['wpcl_variations'] = $order->get_item( $item_id );
                $current_row['wpcl_variations_data'] = array();
                foreach ( $current_row['wpcl_variations']->get_meta_data() as $itemvariation ) {
                    if ( !is_array( $itemvariation->value ) ) {
                        $current_row['wpcl_variations_data'][] = array(
                            'label' => wc_attribute_label( $itemvariation->key ),
                            'value' => wc_attribute_label( $itemvariation->value ),
                        );
                    }
                }
            }
            
            if ( isset( $columns['wpcl_order_total'] ) ) {
                $current_row['wpcl_order_total'] = $order->get_formatted_order_total();
            }
            if ( isset( $columns['wpcl_order_qty'] ) ) {
                
                if ( $split_rows == 'true' ) {
                    $current_row['wpcl_order_qty'] = 1;
                } else {
                    $current_row['wpcl_order_qty'] = $quantity;
                }
            
            }
            $productcount[] = $quantity;
            if ( $order->get_billing_email() ) {
                $data['email_list'][] = $order->get_billing_email();
            }
            $data['data'][] = $current_row;
        }
        
        if ( !empty($data['email_list']) ) {
            $data['email_list'] = array_unique( $data['email_list'] );
            $data['email_list'] = implode( ',', $data['email_list'] );
        }
        
        //		$data['columns'] = $columns;
        $display_data = array();
        foreach ( $data['data'] as $data_row ) {
            $current_row = array();
            foreach ( $columns as $column_key => $column_name ) {
                
                if ( isset( $data_row[$column_key] ) ) {
                    $current_row[] = $this->prep_cell( $column_key, $data_row[$column_key] );
                } else {
                    $current_row[] = $this->prep_cell( $column_key, '' );
                }
            
            }
            $display_data[] = $current_row;
        }
        return array(
            'success'       => true,
            'data'          => $display_data,
            'columns'       => $columns,
            'product_count' => array_sum( $productcount ),
            'email_list'    => $data['email_list'],
        );
    }
    
    public function prep_cell( $option_name, $data )
    {
        $return = '';
        switch ( $option_name ) {
            case 'wpcl_order_number':
                $return = '<a href="' . admin_url( 'post.php' ) . '?post=' . $data . '&action=edit" target="_blank">' . $data . '</a>';
                break;
            case 'wpcl_billing_email':
                $return = '<a href="mailto:' . $data . '">' . $data . '</a>';
                break;
            case 'wpcl_billing_phone':
                $return = '<a href="tel:' . $data . '">' . $data . '</a>';
                break;
            case 'wpcl_customer_id':
                if ( $data ) {
                    $return = '<a href="' . get_admin_url() . 'user-edit.php?user_id=' . $data . '" target="_blank">' . $data . '</a>';
                }
                break;
            case 'wpcl_variations':
                
                if ( !empty($data) && !empty($data->get_meta_data()) ) {
                    $return = '<span style="max-height: 50px; overflow-y: auto; display: block;">';
                    foreach ( $data->get_meta_data() as $itemvariation ) {
                        if ( !is_array( $itemvariation->value ) ) {
                            $return .= '<strong>' . wc_attribute_label( $itemvariation->key ) . '</strong>: &nbsp;' . wc_attribute_label( $itemvariation->value ) . '<br />';
                        }
                    }
                    $return .= '</span>';
                }
                
                break;
            default:
                $return = $data;
                break;
        }
        return $return;
    }

}