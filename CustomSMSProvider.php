<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CustomSMSProvider {
	public $provider = 'as_custom_sms';

    public function init() {
		add_filter('book_oz_plugin_settings', [$this, 'getOptions']);
        add_filter('book_oz_otp_send_'.$this->provider, [$this,'send_otp'], 10, 3);
        add_action('book_sendingSMS_'.$this->provider, [$this,'send_book'],10,5);
        add_action('book_sendingSMSRemind_emp_'.$this->provider, [$this, 'send'], 10,3);
		add_action('book_oz_smsReminder_'.$this->provider, [$this, 'send'], 10, 2); 
    }

	/**
     * Adding custom sms options in plugin settings on sms tab
     *
     * @param  array $settings all plugin settings
     * @return array
     */
    public function getOptions($settings) {
		$settings['sms']['options'][] = [
			'title' => 'Twilio', // SMS method name
			'description' => '',
			'order' => 40,
			// in fields, be sure to add an array of options of type switch
			'fields' => [
				[
					'name' => 'oz_smsType', // here always oz_smsType
					'value' => get_option('oz_smsType') == $this->provider, // here is always the condition
					'type' => 'switch', // here always switch
					'multiple' => false, // here always false
					// в values here always these values
					'values' => [
						[
						'label' => '',
						'value' => $this->provider
						]
					],
					// here we will describe our options. One option, one array. Using Twilio as an example, three options are needed: SID, Token, Account Phone Number or Alphanumeric Sender ID
					'fields' => [
							[
							'title' => 'SID',
							'description' => '',
							'order' => 10,
							'fields' => [
									[
										'name' => 'oz_twilio_sid', // You can come up with any option name. we will write oz_twilio_sid
										'value' => get_option('oz_twilio_sid', ''),
										'type' => 'input',
										'multiple' => false,
										'values' => [],
									],
								]
							],
							[
							'title' => 'Token',
							'description' => '',
							'order' => 10,
							'fields' => [
									[
										'name' => 'oz_twilio_token',
										'value' => get_option('oz_twilio_token', ''),
										'type' => 'input',
										'multiple' => false,
										'values' => [],
									],
								]
							],
							[
							'title' => 'Account Phone Number or Alphanumeric Sender ID',
							'description' => '',
							'order' => 10,
							'fields' => [
									[
										'name' => 'oz_twilio_sender',
										'value' => get_option('oz_twilio_sender', ''),
										'type' => 'input',
										'multiple' => false,
										'values' => [],
									],
								]
							],
						]
					]
				]
                ];
			return $settings;
    }

	/**
     * Send SMS via Custom SMS method
     *
     * @param  string $to phone number
     * @param  string $message text message
     * @param  string $sendTime when sms reminder will send
     * @return array
     */
    public function send($to, $message, $sendTime = null) {
        $sid = get_option('oz_twilio_sid');
        $token = get_option('oz_twilio_token');
        $auth = base64_encode($sid . ":" . $token);
        $from = get_option('oz_twilio_sender');
        $data = array(
            "From" => $from,
            "To" => $to,
            "Body" => do_shortcode($message),
        );
        $url = "https://api.twilio.com/2010-04-01/Accounts/".$sid."/Messages.json";
        $sms = wp_remote_post( $url, array(
            'headers' => [ 'Authorization' => "Basic $auth" ],
            'body'        => $data,
        ) );
        if (is_wp_error($sms)) {
            $sms = [
                'error' => true,
                'text' => $sms->get_error_message(),
            ];
        }
        else {
            $sms = json_decode(wp_remote_retrieve_body($sms), true);
            if ($sms['status'] && $sms['status'] == 'queued') {
                return $sms;
            }
            else {
                $sms = [
                    'error' => true,
                    'text' => $sms['message'],
                    'response' => $sms
                ];
            }
        }
        return $sms;		
    }

	/**
     * Send SMS on booking and return sending status 
     *
     * @param  string $to customer phone number
     * @param  string $message text message
     * @param  string $sendTime when sms reminder will send
     * @return array
     */
    public function send_book($from,$to,$text,$id,$sendTime = 0) {
        $response = $this->send($to, $text, $sendTime);
		if (isset($response['error']) && $response['error']) {
            update_post_meta($id,'oz_sms_error',sanitize_text_field($response['text']));
            add_filter('book_oz_Send_status', function($status) {
                    if (isset($status['id'])) {
                $error = get_post_meta($status['id'],'oz_sms_error',true);
                if ($error)
                $status['twilio_error'] = __('SMS Error! Curl error', 'book-appointment-online').': '.$error; 
                    }
                return $status;
                });
        }
    }
    
    /**
     * Send OTP SMS
     *
     * @param  mixed false or array with data
     * @param  string $to phone number
     * @param  string $message text message
     * @return array
     */
    public function send_otp($response, $to, $message) {
        $response = $this->send($to, $message);
        if (isset($response['error']) && $response['error']) {
            return $response;
        }
        else {
            return [
                'success' => true,
            ];
        }
    }    
}