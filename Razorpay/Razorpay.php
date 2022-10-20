<?php 

defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH."libraries/razorpay/razorpay-php/Razorpay.php");

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;



 class Razorpay extends CI_Controller{
     function __construct() {
     parent::__construct();
}
   

public function pay($token,$txtamount)
	{
	    
	    $user_id = $this->session->userdata('user_id');

	    
            $this->db->select('*');
            $this->db->from('users');
            $this->db->where('id',$user_id);
            $query = $this->db->get()->row();


     
            if(!$query || !$token || !$txtamount){
            
       
                redirect($_SERVER['HTTP_REFERER']);

            }
	    
		$api = new Api(RAZOR_KEY, RAZOR_SECRET_KEY);
	
		$_SESSION['payable_amount'] = $txtamount;

		$razorpayOrder = $api->order->create(array(
			'receipt'         => rand(),
			'amount'          => $_SESSION['payable_amount'] * 100, // 2000 rupees in paise
			'currency'        => 'INR',
			'payment_capture' => 1 // auto capture
		));


		$amount = $razorpayOrder['amount'];

		$razorpayOrderId = $razorpayOrder['id'];

		$_SESSION['razorpay_order_id'] = $razorpayOrderId;
		   

		$data = $this->prepareData($amount,$razorpayOrderId);
		
		$this->setSaveData($razorpayOrderId,$token,$amount);

		$this->load->view('rezorpay',array('data' => $data));
	}


	public function verify()
	{
		$success = true;
		$error = "payment_failed";
		if (empty($_POST['razorpay_payment_id']) === false) {
			$api = new Api(RAZOR_KEY, RAZOR_SECRET_KEY);
		try {
				$attributes = array(
					'razorpay_order_id' => $_SESSION['razorpay_order_id'],
					'razorpay_payment_id' => $_POST['razorpay_payment_id'],
					'razorpay_signature' => $_POST['razorpay_signature']
				);
				$api->utility->verifyPaymentSignature($attributes);
			} catch(SignatureVerificationError $e) {
				$success = false;
				$error = 'Razorpay_Error : ' . $e->getMessage();
			}
		}
		
		$this->setUpdateData($_SESSION['razorpay_order_id'], $_POST['razorpay_payment_id']);
		
	
		if ($success === true) {
			
			$this->session->set_flashdata('success','Payment successful !.');

		}
		else {
		  
		    $this->session->set_flashdata('error','Payment failed !.');

		}
	
			redirect(base_url('user/account'));

	}


	public function prepareData($amount,$razorpayOrderId)
	{
	    
		$user_id = $this->session->userdata('user_id');
		$this->db->select('*');
		$this->db->from('users');
		$this->db->where('id',$user_id);
		$query = $this->db->get()->row();

		$data = array(
			"key" => RAZOR_KEY,
			"amount" => $amount,
			"name" => "asaanhouse",
			"description" => "asaanhouse",
            "image"=> base_url('assets/images/favicon-icon.png'),
			"prefill" => array(
				"name"  =>  $query->name,
				"email"  => $query->email,
				"contact" =>  $query->mobile,
			),
			"notes"  => array(
				//"address"  => "Hello World",
				"merchant_order_id" => rand(),
			),
			"theme"  => array(
				"color"  => "#861f41"
			),
			"order_id" => $razorpayOrderId,
		);
		return $data;
	}


	public function setSaveData($razorpayOrderId,$token,$amount)
	{
		$user_id = $this->session->userdata('user_id');
		$this->db->select('*');
		$this->db->from('users');
		$this->db->where('id',$user_id);
		$query = $this->db->get()->row();

        $data = [
                'token' => $token,
                'user_id' => $user_id,
                'order_id' => $razorpayOrderId,
                'payment_id' => '',
                'amount' => $amount,
                'name' => $query->name,
                'email' => $query->email,
                'phone' => $query->mobile,
                'currency' => 'INR',
                'payment_status' => 'pending',       
                ];
            $insert = $this->db->insert('payment_details', $data); // save this to database

	}



	public function setUpdateData($order_id, $payment_id)
	{

        if($payment_id && $order_id){
                $url= 'https://api.razorpay.com/v1/payments/'.$payment_id;
        
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_USERPWD, RAZOR_KEY . ':' . RAZOR_SECRET_KEY);
                $result = curl_exec($ch);
                if (curl_errno($ch)) {
                echo 'Error:' . curl_error($ch);
                }
                curl_close($ch);
                
                $result = json_decode($result, true);
                //print_r($result);

                $data = [ 'payment_id' => $payment_id, 'payment_status' => $result['status'] ];
                
                $this->db->where('order_id', $order_id);
                $insert = $this->db->update('payment_details', $data);
        
        }
        return true;

    
	}
	


   
    
}


?>