<?php

namespace jakharbek\facebooksharer;

class Facebook
{
    /**
     * @var secret id from developers.facebook.com
     */
    public $secret_id;
    /**
     * @var app id from developers.facebook.com
     */
    public $app_id;
    /**
     * @var string page|user|group and other
     */
    public $type = "page";
    /**
     * @var string name as id for idientification your element
     */
    public $name_as_id = "";
    /**
     * @var string api version
     */
    public $version = "v2.2";
    /**
     * @var array $permissions
     */
    public $permissions = ['manage_pages', 'publish_pages'];
    /**
     * @var string $login_url
     */
    public $login_url = "";
    /**
     * @var string $callback_url
     */
    public $callback_url = "";


    private $helper;
    private $fb;

    private $accessToken;
    private $accounts;

    /**
     * initial data
     */
    public function init(){
        $this->instance();
    }

    /**
     * instace (initial data)
     */
    public function instance(){
        $this->fb = new \Facebook\Facebook([
            'app_id' => $this->app_id, // Replace {app-id} with your app id
            'app_secret' => $this->secret_id,
            'default_graph_version' => $this->version,
        ]);
        $this->helper = $this->fb->getRedirectLoginHelper();
    }

    /**
     * @param bool $redirect
     * @return mixed
     * Делает авторизацию на facebook
     * Данный метод вы должны вставить в скрипт входа
     */
    public function login($redirect = true)
    {
        $secret_id = $this->secret_id;
        $app_id = $this->app_id;
        $fb = $this->fb;
        $helper = $this->helper;
        $permissions = $this->permissions;
        $loginUrl = $helper->getLoginUrl($this->callback_url, $permissions);
        if(!$redirect){return $loginUrl;}
        header("Location:". $loginUrl);
        exit();
    }

    /**
     * @return mixed
     * Этот метод обратный связи
     * Данный метод в должны вставить в скрипт обратный связи
     */
    public function callback(){
        $app_id = $this->app_id;
        $fb = $this->fb;
        $helper = $this->helper;
        if (isset($_GET['state'])) {
            $helper->getPersistentDataHandler()->set('state', $_GET['state']);
        }
        try {
            $accessToken = $helper->getAccessToken();
        } catch(\Facebook\Exceptions\FacebookResponseException $e) {
            // When Graph returns an error
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch(\Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }

        if (! isset($accessToken)) {
            if ($helper->getError()) {
                header('HTTP/1.0 401 Unauthorized');
                echo "Error: " . $helper->getError() . "\n";
                echo "Error Code: " . $helper->getErrorCode() . "\n";
                echo "Error Reason: " . $helper->getErrorReason() . "\n";
                echo "Error Description: " . $helper->getErrorDescription() . "\n";
            } else {
                header('HTTP/1.0 400 Bad Request');
                echo 'Bad request';
            }
            exit;
        }
        $oAuth2Client = $fb->getOAuth2Client();
        $tokenMetadata = $oAuth2Client->debugToken($accessToken);
        $tokenMetadata->validateAppId($app_id);
        $tokenMetadata->validateExpiration();
        if (! $accessToken->isLongLived()) {
            try {
                $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
            } catch (\Facebook\Exceptions\FacebookSDKException $e) {
                echo "<p>Error getting long-lived access token: " . $helper->getMessage() . "</p>\n\n";
                exit;
            }
        }

        $_SESSION['fb_access_token'] = (string) $accessToken;
        $this->accessToken = $accessToken;
        $this->setToken();
        return $accessToken;
    }

    /**
     * @return bool|string
     */
    public function setToken(){
        $file = "facebook.token";
        return file_put_contents($file, $this->accessToken);
    }

    /**
     * @return bool|string
     */
    public function getToken(){
        $file = "facebook.token";
        return file_get_contents($file);
    }

    /**
     * @param string $messsage
     * @return array
     */
    public function post($messsage = "",$link = ""){
        $app_id = $this->app_id;
        $fb = $this->fb;
        $helper = $this->helper;
        $accessToken = $this->getToken();
        $this->accounts = $fb->get('/me/accounts',$accessToken);
        $pages = $this->accounts->getGraphEdge()->asArray();
        $result = [];
        foreach ($pages as $key) {
            if ($key['name'] == $this->name_as_id) {

                $post = $fb->post('/' . $key['id'] . '/feed',
                    array(
                        'message'=> $messsage,
                        'link' => $link,
                    ),
                    $key['access_token']);
                $post = $post->getGraphNode()->asArray();
                $result[] = $post;
            }
        }
        return $result;
    }

}