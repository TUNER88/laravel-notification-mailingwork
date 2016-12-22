<?php

namespace NotificationChannels\Mailingwork;

use Illuminate\Support\Str;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use NotificationChannels\Mailingwork\Exceptions\CouldNotSendNotification;

class Mailingwork
{
    /** @var HttpClient HTTP Client */
    protected $http;

    /**
     * Mailingwork username
     * @var mixed|null
     */
    protected $username = null;

    /**
     * Mailingwork password
     * @var mixed|null
     */
    protected $password = null;

    /**
     * Sender name
     * @var null
     */
    protected $fromName = null;

    /**
     * Sender address
     * @var null
     */
    protected $fromAddress = null;

    /**
     * Mailingwork API host
     * @var string
     */
    protected $apiHost = "login.mailingwork.de";

    /**
     * Mailingwork API path
     * @var string
     */
    protected $apiPath = "webservice/webservice/json";

    /**
     * Mailingwork API protocol
     * @var string
     */
    protected $apiProtocol = "https";

    /**
     * Mailingwork constructor.
     * @param array $config
     * @param HttpClient|null $httpClient
     */
    public function __construct(array $config, HttpClient $httpClient = null)
    {
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->fromName = $config['from']['name'];
        $this->fromAddress = $config['from']['address'];
        $this->apiProtocol = $config['ssl'] ? "https" : "http";

        $this->http = $httpClient;
    }

    /**
     * Get HttpClient.
     *
     * @return HttpClient
     */
    protected function httpClient()
    {
        return $this->http ?: $this->http = new HttpClient();
    }


    public function sendMessage(MailingworkMessage $message)
    {
        $id = $this->createEmail($message);
        $this->activateEmail($id);
        return $this->sendRequest('sendMessage', $params);
    }

    private function createEmail(MailingworkMessage $message){
        $response = $this->sendRequest('createemail', [
            'subject' => $this->buildSubject($message),
            'senderName' => $this->fromName,
            'senderEmail' => $this->fromAddress,
            'behaviour' => 'campaign',
            'behavior' => 'campaign',
            'listId' => '',
            'targetgroupId' => '',
            'html' => $message->render()
        ]);

        // return email id
        return $response['result'];
    }

    private function activateEmail($id){
        $response = $this->sendRequest('activateemail', ['emailId' => $id]);
    }

    private function sendEmail(){

    }

    /**
     * Set the subject for the message.
     *
     * @param  \Illuminate\Mail\Message  $message
     * @return $this
     */
    protected function buildSubject($message)
    {
        if (!$message->subject) {
            $message->subject(Str::title(Str::snake(class_basename($message), ' ')));
        }

        return $message->subject;
    }

    /**
     * Create API baseurl based on protocol, host, and api path
     *
     * @return string
     */
    protected function getBaseUrl(){
        return sprintf("%s://%s/%s", $this->apiProtocol, $this->apiHost, $this->apiPath);
    }

    /**
     * Create endpoint url
     *
     * @param string $endpoint
     * @return string
     */
    protected function getEndpointUrl(string $endpoint){
        return sprintf("%s/%s", $this->getBaseUrl(), $endpoint);
    }

    /**
     * Send an API request and return response.
     *
     * @param $endpoint
     * @param $params
     *
     * @throws CouldNotSendNotification
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function sendRequest($endpoint, $params)
    {
        if (!$this->username || !$this->password) {
            throw CouldNotSendNotification::credentialsNotProvided('You must provide mailingwork credentials to make any API requests.');
        }

        // append credentials
        $params['username'] = $this->username;
        $params['password'] = $this->password;

        try {
            $response = $this->httpClient()->post($this->getEndpointUrl($endpoint), [
                'form_params' => $params
            ]);
            $data = json_decode($response->getBody(), true);

            if($data['error'] !== 0){
                throw CouldNotSendNotification::mailingworkRespondedWithAnMessage($data['message']);
            }
            return $data;
        } catch (ClientException $exception) {
            throw CouldNotSendNotification::mailingworkRespondedWithAnError($exception);
        } catch (\Exception $exception) {
            throw CouldNotSendNotification::couldNotCommunicateWithMailingwork();
        }
    }
}