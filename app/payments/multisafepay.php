<?php

/**
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the MultiSafepay plugin
 * to newer versions in the future. If you wish to customize the plugin for your
 * needs please document your changes and make backups before you update.
 *
 * @category MultiSafepay
 * @package Connect
 * @author TechSupport <techsupport@multisafepay.com>
 * @copyright Copyright (c) 2018 MultiSafepay, Inc. (https://www.multisafepay.com)
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

use Tygh\Http;
use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}
if (isset($_GET['type'])) {
    if ($_GET['type'] == "feed") {
        parseFeed();
    }
}


if (defined('PAYMENT_NOTIFICATION')) {
    if (isset($_REQUEST['type'])) {
        if ($_REQUEST['type'] == 'initial') {
            $url = 'payment_notification.return&payment=multisafepay_ideal&transactionid=' . $_REQUEST['transactionid'];
            $url = fn_url($url, AREA, 'current');

            echo '<a href="' . $url . '" >Keer terug naar de website.</a>';
            exit;
        }
    }


    if (($mode == 'notify' || $mode == 'return') && !empty($_REQUEST['transactionid'])) {
        if ($_REQUEST['transactionid']) {
            $pp_response = array();
            $order_info = fn_get_order_info($_REQUEST['transactionid']);

            if (empty($processor_data)) {
                $processor_data = fn_get_processor_data($order_info['payment_id']);
            }

            $pp_response = array();
            $order_id = $_REQUEST['transactionid'];

            require_once(dirname(__FILE__) . '/MultiSafepay.combined.php');

            if ($processor_data['processor_params']['mode'] == 'T') {
                $test = true;
            } else {
                $test = false;
            }

            $msp = new MultiSafepay();
            $msp->test = $test;
            $msp->merchant['account_id'] = $processor_data['processor_params']['account'];
            $msp->merchant['site_id'] = $processor_data['processor_params']['site_id'];
            $msp->merchant['site_code'] = $processor_data['processor_params']['securitycode'];
            $msp->transaction['id'] = $order_id;
            $status = $msp->getStatus();
            $details = $msp->details;
            $amount = $details['customer']['amount'];
            $order_id = $details['transaction']['id'];
            $pp_response['transaction_id'] = $order_id;

            $msp_statuses = $processor_data['processor_params']['statuses'];

            if ($order_info['status'] != 'P' && $order_info['status'] != 'C' || $status == "refunded" || $status == "partial_refunded") {

                switch ($status) {
                    case "initialized":
                        $pp_response['order_status'] = $msp_statuses['initialized'];
                        $pp_response['reason_text'] = 'Transaction Initialized';

                        break;
                    case "completed":
                        $pp_response['order_status'] = $msp_statuses['completed'];
                        $pp_response['reason_text'] = 'Transaction completed';
                        break;
                    case "uncleared":
                        $pp_response['order_status'] = $msp_statuses['uncleared'];
                        $pp_response['reason_text'] = 'Transaction uncleared';
                        break;
                    case "reserved":
                        $pp_response['order_status'] = $msp_statuses['reserved'];
                        $pp_response['reason_text'] = 'Transaction reserved';
                        break;
                    case "void":
                        $pp_response['order_status'] = $msp_statuses['voided'];
                        $pp_response['reason_text'] = 'Transaction void';
                        break;
                    case "declined":
                        $pp_response['order_status'] = $msp_statuses['declined'];
                        $pp_response['reason_text'] = 'Transaction declined';
                        break;
                    case "reversed":
                        $pp_response['order_status'] = $msp_statuses['reversed'];
                        $pp_response['reason_text'] = 'Transaction reversed';
                        break;
                    case "refunded":
                        $pp_response['order_status'] = $msp_statuses['refunded'];
                        $pp_response['reason_text'] = 'Transaction refunded';
                        break;
                    case "partial_refunded":
                        $pp_response['order_status'] = $msp_statuses['partial_refunded'];
                        $pp_response['reason_text'] = 'Transaction partial refunded';
                        break;
                    case "expired":
                        $pp_response['order_status'] = $msp_statuses['expired'];
                        $pp_response['reason_text'] = 'Transaction expired';
                        break;
                    case "cancelled":
                        $pp_response['order_status'] = $msp_statuses['cancelled'];
                        $pp_response['reason_text'] = 'Transaction cancelled';
                        break;
                    default:
                        break;
                }


                if ($details['ewallet']['id'] != '' && $details['paymentdetails']['type'] != 'BANKTRANS') {
                    if ($status == 'initialized' || $status == 'expired') {
                        fn_change_order_status($_REQUEST['transactionid'], $pp_response['order_status'], '');
                        //fn_order_placement_routines($_REQUEST['transactionid'], false);
                    } else {
                        fn_change_order_status($_REQUEST['transactionid'], $pp_response['order_status'], '');
                        fn_finish_payment($order_id, $pp_response);
                    }
                } elseif ($details['ewallet']['id'] != '' && $details['paymentdetails']['type'] == 'BANKTRANS' && $mode != 'return') {
                    if ($status == 'initialized') {
                        fn_change_order_status($_REQUEST['transactionid'], $msp_statuses['initialized'], '');
                    } else {
                        fn_change_order_status($_REQUEST['transactionid'], $pp_response['order_status'], '');
                        fn_finish_payment($order_id, $pp_response);
                    }
                }
            }
        }


        if ($mode == 'return') {

            if ($details['paymentdetails']['type'] == 'BANKTRANS') {
                $order_info = fn_get_order_info($_REQUEST['transactionid'], true);
                $order_id = $_REQUEST['transactionid'];
                $processor_data = fn_get_payment_method_data($order_info['payment_id']);

                $msp_statuses = $processor_data['processor_params']['statuses'];

                $order_info = fn_get_order_info($_REQUEST['transactionid'], true);
                //if ($order_info['status'] == 'N' || $order_info['status'] == $msp_statuses['initialized'] ) {
                fn_change_order_status($_REQUEST['transactionid'], 'O', '');
                //}


                fn_order_placement_routines('route', $_REQUEST['transactionid']);

                exit;
            } else {
                $order_info = fn_get_order_info($_REQUEST['transactionid'], true);
                $order_id = $_REQUEST['transactionid'];
                $processor_data = fn_get_payment_method_data($order_info['payment_id']);

                $msp_statuses = $processor_data['processor_params']['statuses'];

                if ($order_info['status'] == 'N') {
                    fn_change_order_status($_REQUEST['transactionid'], $msp_statuses['initialized'], '');
                }

                fn_order_placement_routines('route', $_REQUEST['transactionid']);
                exit;
            }
        }

        if (isset($_REQUEST['type'])) {
            $url = 'payment_notification.return&payment=multisafepay_ideal&transactionid=' . $order_id;
            $url = fn_url($url, AREA, 'current');
            echo '<a href="' . $url . '" >Keer terug naar de website.</a>';
        } else {
            echo "ok";
        }
        exit;
    } elseif ($mode == 'cancel') {
        $order_info = fn_get_order_info($_REQUEST['transactionid'], true);
        $order_id = $_REQUEST['transactionid'];
        $processor_data = fn_get_payment_method_data($order_info['payment_id']);


        $msp_statuses = $processor_data['processor_params']['statuses'];

        $pp_response['order_status'] = $msp_statuses['cancelled'];
        $pp_response["reason_text"] = fn_get_lang_var('text_transaction_cancelled');

        fn_finish_payment($_REQUEST['transactionid'], $pp_response);
        fn_order_placement_routines('route', $_REQUEST['transactionid']);
        exit;
    } elseif ($mode == 'process') {
        $pp_response = array();
    }
    exit;
} else {
    $itemlist = $order_info["products"];
    if (is_array($itemlist)) {
        $cart_items = "<ul>\n";
        foreach ($itemlist as $product) {
            $product_price = fn_format_price_by_currency($product['price'], CART_PRIMARY_CURRENCY, CART_SECONDARY_CURRENCY);
            $cart_items .= "<li>" . $product['amount'] . " x : " . $product['product'] . " : " . $product_price . "</li>\n";
        }
        $cart_items .= "</ul>\n";
    }


    if ($processor_data['processor_params']['mode'] == 'T') {
        $test = true;
    } else {
        $test = false;
    }

    //MSP SET DATA FOR TRANSACTION REQUEST

    $ip = fn_get_ip();
    require_once(dirname(__FILE__) . '/MultiSafepay.combined.php');
    $msp = new MultiSafepay();
    $msp->test = $test;
    $msp->merchant['account_id'] = $processor_data['processor_params']['account'];
    $msp->merchant['site_id'] = $processor_data['processor_params']['site_id'];
    $msp->merchant['site_code'] = $processor_data['processor_params']['securitycode'];
    //$msp->merchant['notification_url'] 	= 	Registry::get('config.current_location') . "/$index_script?dispatch=payment_notification.notify&payment=multisafepay_".strtolower($processor_data['processor_params']['gateway'])."&type=initial";
    //$msp->merchant['cancel_url']       	= 	Registry::get('config.current_location') . "/$index_script?dispatch=payment_notification.cancel&payment=multisafepay_".strtolower($processor_data['processor_params']['gateway'])."&transactionid=".$order_id;
    //$msp->merchant['redirect_url'] 	   	= 	Registry::get('config.current_location') . "/$index_script?dispatch=payment_notification.return&payment=multisafepay_".strtolower($processor_data['processor_params']['gateway']);

    $gateway_url_postfix = strtolower($processor_data['processor_params']['gateway']);
    if ($gateway_url_postfix == "mistercash") { //hotfix for bancontact/mistercash url
        $gateway_url_postfix = "bancontact";
    } elseif ($gateway_url_postfix == "psafecard") { //hotfix for psafecard
        $gateway_url_postfix = "paysafecard";
    } elseif ($gateway_url_postfix == "inghome") { //hotfix for INGHome
        $gateway_url_postfix = "ing";
    }

    $url = 'payment_notification.notify&payment=multisafepay_' . $gateway_url_postfix . '&type=initial';
    $url = fn_url($url, AREA, 'current');

    $msp->merchant['notification_url'] = $url;

    $url = 'payment_notification.cancel&payment=multisafepay_' . $gateway_url_postfix . '&transactionid=' . $order_id;
    $url = fn_url($url, AREA, 'current');
    $msp->merchant['cancel_url'] = $url;

    $url = 'payment_notification.return&payment=multisafepay_' . $gateway_url_postfix;
    $url = fn_url($url, AREA, 'current');
    $msp->merchant['redirect_url'] = $url;


    $msp->merchant['close_window'] = true;
    //$msp->customer['locale']           	= 	$processor_data['processor_params']['language'];
    $msp->customer['locale'] = isset($order_info['lang_code']) ? strtolower($order_info['lang_code']) : $processor_data['processor_params']['language'];
    $msp->customer['locale'] .= '_' . $order_info['b_country'];

    $msp->customer['firstname'] = $order_info['b_firstname'];
    $msp->customer['lastname'] = $order_info['b_lastname'];

    $msp->customer['zipcode'] = $order_info['b_zipcode'];
    $msp->customer['city'] = $order_info['b_city'];
    $msp->customer['state'] = $order_info['b_state'];
    $msp->customer['email'] = $order_info['email'];
    $msp->customer['phone'] = $order_info['phone'];
    $msp->customer['country'] = $order_info['b_country'];
    $msp->customer['ipaddress'] = $ip['host'];
    $msp->customer['forwardedip'] = $ip['proxy'];
    $msp->parseCustomerAddress($order_info['b_address']);

    $msp->delivery['firstname'] = $order_info['s_firstname'];
    $msp->delivery['lastname'] = $order_info['s_lastname'];
    $msp->delivery['zipcode'] = $order_info['s_zipcode'];
    $msp->delivery['city'] = $order_info['s_city'];
    $msp->delivery['state'] = $order_info['s_state'];
    $msp->delivery['phone'] = $order_info['s_phone'];
    $msp->delivery['country'] = $order_info['s_country'];
    $msp->parseDeliveryAddress($order_info['s_address']);

    $msp->transaction['id'] = $order_id;
    $msp->transaction['currency'] = ($order_info['secondary_currency'] ? $order_info['secondary_currency'] : $processor_data['processor_params']['currency']);
    $msp->cart->currency = $msp->transaction['currency'];
    $msp->transaction['amount'] = fn_format_price_by_currency($order_info['total'], CART_PRIMARY_CURRENCY, CART_SECONDARY_CURRENCY) * 100;
    $msp->transaction['description'] = 'Order #' . $msp->transaction['id'];
    $msp->transaction['items'] = $cart_items;
    $msp->transaction['gateway'] = getGateway($processor_data['processor_params']['gateway']);
    $msp->plugin_name = 'CS-Cart 4.x';
    $msp->version = '1.3.0';

    $msp->plugin['shop'] = 'CS-Cart';
    $msp->plugin['shop_version'] = '4';
    $msp->plugin['plugin_version'] = '1.3.0';
    $msp->plugin['partner'] = '';
    $msp->plugin['shop_root_url'] = Registry::get('config.current_location');

    $taxes = array();
    $taxes['no-tax'] = 0;

    //Add the products
    foreach ($order_info['products'] as $item) {
        $product_data = fn_get_product_data($item['product_id'], $_SESSION['auth'], $order_info['lang_code'], '', true, true, true, true, false, true, true);

        // Get (first) Product tax
        if (!empty($product_data['tax_ids'])){
            $product_tax_id = reset ($product_data['tax_ids']);
        }

        $product_price = $item['price'];

        if (empty ($product_tax_id)) {
            $taxid = 'no-tax';
        }else{
            $rate  = $order_info['taxes'][$product_tax_id]['rate_value'];
            $taxid = $order_info['taxes'][$product_tax_id]['description'] . '-' . $rate;;
            $taxes[$taxid] = $rate;

            if ( $order_info['taxes'][$product_tax_id]['price_includes_tax'] == 'Y'){
                $tax = ($product_price / (100 + $rate)) * $rate;
                $product_price = $product_price - $tax;
            }
        }

        $c_item = new MspItem($item['product'], '', $item['amount'], fn_format_price_by_currency($product_price, CART_PRIMARY_CURRENCY, CART_SECONDARY_CURRENCY), 'KG', 0);
        $c_item->SetMerchantItemId($item['product_code']);
        $c_item->SetTaxTableSelector($taxid);
        $msp->cart->AddItem($c_item);
    }


    //add shipping line item
    $shipping_cost = $order_info['shipping_cost'];
    if ($shipping_cost >0) {

        // Get (first) Shipping method
        $shipping = reset ($order_info['shipping']);

        // Get (first) Shipping tax
        if (!empty($shipping['taxes'])) {
            $shipping_tax = reset($shipping['taxes']);
        }

        if (empty ($shipping_tax)) {
            $taxid = 'no-tax';
        }else{
            $rate  = $shipping_tax['rate_value'];
            $taxid = $shipping_tax['description'] . '-' . $rate;
            $taxes[$taxid] = $rate;

            if ( $shipping_tax['price_includes_tax'] == 'Y'){
                $shipping_cost = $shipping_cost - $shipping_tax['tax_subtotal'];
            }
        }

        $c_item = new MspItem($shipping['shipping'], __('Shipping'), 1, fn_format_price_by_currency($shipping_cost, CART_PRIMARY_CURRENCY, CART_SECONDARY_CURRENCY), 'KG', 0);
        $c_item->SetMerchantItemId('msp-shipping');
        $c_item->SetTaxTableSelector($taxid);
        $msp->cart->AddItem($c_item);
    }


    //Add payment surcharge
    $total_surcharge = $order_info['payment_surcharge'];
    if ($total_surcharge >0) {

        // Get (first) Surcharge tax
        if (!empty($order_info['payment_method']['tax_ids'])) {
            $surcharge_tax_id = reset($order_info['payment_method']['tax_ids']);
        }

        if (empty ($surcharge_tax_id)) {
            $taxid = 'no-tax';
        }else{
            $rate  = $order_info['taxes'][$surcharge_tax_id]['rate_value'];
            $taxid = $order_info['taxes'][$surcharge_tax_id]['description'] . '-' . $rate;;
            $taxes[$taxid] = $rate;

            if ( $order_info['taxes'][$surcharge_tax_id]['price_includes_tax'] == 'Y'){
                $tax = ($total_surcharge / (100 + $rate)) * $rate;
                $total_surcharge = $total_surcharge - $tax;
            }
        }

        $surcharge_title = $order_info['payment_method']['surcharge_title'] ?: __('payment_surcharge');
        $c_item = new MspItem($surcharge_title,'Surcharge',  1, fn_format_price_by_currency($total_surcharge, CART_PRIMARY_CURRENCY, CART_SECONDARY_CURRENCY), 'KG', 0);
        $c_item->SetMerchantItemId('Surcharge');
        $c_item->SetTaxTableSelector($taxid);
        $msp->cart->AddItem($c_item);
    }


    if (isset($order_info['promotions'])) {
        foreach ($order_info['promotions'] as $key => $value) {
            if ($order_info['subtotal_discount'] != '0.00') {
                $discount_price = $order_info['subtotal_discount'];
                $c_item = new MspItem($value['name'], 'Discount Price', 1, ('-' . fn_format_price_by_currency($discount_price, CART_PRIMARY_CURRENCY, CART_SECONDARY_CURRENCY)));
                $c_item->SetTaxTableSelector('no-tax');
                $msp->cart->AddItem($c_item);
            }
        }
    }


    $taxrule = new MspDefaultTaxRule( $taxes['no-tax'], false);
    $msp->cart->AddDefaultTaxRules($taxrule);

    //add available tax rates ..
    foreach ($taxes as $taxname => $percentage) {
        $taxtable = new MspAlternateTaxTable($taxname, 'true');
        $taxrule = new MspAlternateTaxRule($percentage/100);
        $taxtable->AddAlternateTaxRules($taxrule);
        $msp->cart->AddAlternateTaxTables($taxtable);
    }



    if ($processor_data['processor_params']['gateway'] == 'IDEAL' && isset($order_info['payment_info']['issuer'])) {
        $msp->extravars = $order_info['payment_info']['issuer'];
    }

    if (in_array ($processor_data['processor_params']['gateway'], array ('IDEAL', 'KBC', 'INGHOME', 'ALIPAY', 'PAYPAL'))) {
        $url = $msp->startDirectXMLTransaction();
    } else {
        $url = $msp->startCheckout();
    }
    if (isset($processor_data['processor_params']['debug'])) {
        if ($processor_data['processor_params']['debug'] == 'YES') {
            echo '<b style="color:red">MultiSafepay data:</b>';
            echo '<pre>';
            print_r($msp);
            echo '</pre>';
            echo '<br /><b style="color:red">CS-Cart order data:</b>';
            echo '<pre>';
            print_r($order_info);
            echo '</pre>';
            exit;
        }
    }

    if (!isset($msp->error)) {
        fn_redirect($url, true, true);
        exit;
    } else {
        fn_set_notification('E', "There was an error while processing your transaction: (Code: $msp->error)", "");

        $url = fn_url("checkout.cart", AREA, 'current');

        fn_redirect($url);
    }
    exit;
}

function parseFeed()
{
    echo 'parce feed';
    exit;
}

function getGateway($gateway_code)
{
    return ($gateway_code == "WALLET") ? "" : $gateway_code;
}

?>