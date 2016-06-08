<?php 

require_once '/vendor/autoload.php';

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

class MessengerBot {

    private $api = 'https://graph.facebook.com/v2.6/';

    private $access_token;

    private $chat_message;

    private $sender;
    private $respond_message;

    public function __construct()
    {
        $this->access_token = getenv('ACCESS_TOKEN');
    }

    public function listen()
    {
        $this->chat_message = json_decode(file_get_contents('php://input'), true);
        if(isset($this->chat_message['entry'][0]['messaging'][0]['message']) && !empty($this->chat_message['entry'][0]['messaging'][0]['message'])){
            $this->prepare_message();
            $this->respond();
        }
    }

    public function prepare_message() {
        if(isset($this->chat_message['entry'][0]['messaging'][0]['message']) && !empty($this->chat_message['entry'][0]['messaging'][0]['message'])){
            $this->sender = $this->chat_message['entry'][0]['messaging'][0]['sender']['id'];
            $this->respond_message = $this->createMessage( $this->chat_message['entry'][0]['messaging'][0]['message']['text'] );
        }
    }

    public function createMessage( $message )
    {
        $hi = strpos( strtolower(trim($message)), 'hi' );
        $hello = strpos( strtolower(trim($message)), 'hello' );

        if ($hi !== false) {
            return $this->generateMessageBody(['text' => 'Hello there!']);
        }

        if ($hello !== false) {
            return $this->generateMessageBody(['text' => 'Hi there!']);
        }

    }

    public function generateMessageBody($data, $type = "text") {

        $data = (object) $data;

        switch ($type) {
            case 'image':
                if ($data->image_url) {
                    $template = '"attachment":{
                                    "type":"image",
                                    "payload":{
                                        "url":"' . $data->image_url . '"
                                    }
                                }';
                }
                break;
            case 'buttons':
                if ($data->buttons) {
                    $template = '"attachment":{
                                      "type":"template",
                                      "payload":{
                                        "template_type":"button",
                                        "text":"' . $data->text . '",
                                        "buttons":[
                                            {
                                                "type":"web_url",
                                                "url":"' . $data->web_url . '",
                                                "title":"' . $data->web_url_text . '"
                                            },
                                            {
                                                "type":"postback",
                                                "title":"' . $data->postback_title . '",
                                                "payload":"' . $data->postback_url . '"
                                            }
                                        ]
                                      }
                                    }';
                }
                break;
            default:
                if ($data->text) {
                    $template = '"text":"' . $data->text . '"';
                }
                break;
        }

        return $template;
    }

    public function respond() {
        //API Url
        $url = $this->api . 'me/messages?access_token=' . $this->access_token;

        $this->log( $url );

        /*//The JSON data.
        $jsonData = array(
                array( 'recipient' => array( 
                    'id'    => $this->sender ) ),
                array( 'message' => array( 
                    'text'  => $this->respond_message ) ),
            );

        //Encode the array into JSON.
        $jsonDataEncoded = json_encode($jsonData);*/



        $jsonData = '{
            "recipient":{
                "id":"'.$this->sender.'"
            },
            "message":{
                '.$this->respond_message.'
            }
        }';
         
        //Encode the array into JSON.
        $jsonDataEncoded = $jsonData;

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , false);
            //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST , 2);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $result = curl_exec($ch);

            if ($result === false) {
                $this->log( curl_error($ch) );
            } else {
                $this->log( 'Response was successfully sent.' );
            }

            curl_close($ch);
        } catch (Exception $e) {
            $this->log( 'Error doing CURL request.' );
        }

    }

    public function verify()
    {
        if (isset($this->inputs['hub_challenge'])) {
            return $this->inputs['hub_challenge'];
        }
    }

    public function log( $log, $subject = 'App Logs', $file = 'logs.txt' ) {
        if ( $log )
            error_log(
                "========================================" . "\n" .
                date("r") . " - " .
                $_SERVER['REMOTE_ADDR'] . " - " .
                $subject . " - " .
                print_r($log, true) ."\n". "========================================" . "\n",
                3,
            $file);
    }

}

$chatApp = new MessengerBot();
$chatApp->listen();

if (isset($_GET['hub_verify_token']) && $_GET['hub_verify_token'] === "gno_chat_test_token") {
    $chatApp->inputs = $_GET;
    echo $chatApp->verify();
}

/*$query = $_POST;

$myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
fwrite($myfile, print_r($query,true));
fclose($myfile);
*/
?>