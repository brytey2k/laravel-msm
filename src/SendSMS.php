<?php

declare(strict_types=1);

namespace OrkhanShukurlu\MSM;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use OrkhanShukurlu\MSM\Exceptions\SMSNotSentException;
use OrkhanShukurlu\MSM\Models\MSMLog;
use Throwable;

use function parse_str;

final class SendSMS
{
    /**
     * Api url provided by MSM.
     *
     * @var string
     */
    private const URL = 'https://api.msm.az/sendsms';

    /**
     * Username provided by MSM.
     *
     * @var string
     */
    private string $username;

    /**
     * Password provided by MSM.
     *
     * @var string
     */
    private string $password;

    /**
     * Sender name provided by MSM.
     *
     * @var string
     */
    private string $sender;

    /**
     * Enable or disable database SMS logging mode.
     *
     * @var bool
     */
    private bool $logging;

    /**
     * Query parameters for request.
     *
     * @var array
     */
    private array $query = [];

    /**
     * Response code from request.
     *
     * @var string|null
     */
    private ?string $code;

    /**
     * Response text from request.
     *
     * @var string|null
     */
    private ?string $text;

    /**
     * Create a new send SMS instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->username = (string) config('msm.username', '');
        $this->password = (string) config('msm.password', '');
        $this->sender = (string) config('msm.sender', '');
        $this->logging = (bool) config('msm.logging', false);
    }

    /**
     * Send SMS to phone number.
     *
     * @param string $phone
     * @param int|string $message
     *
     * @throws SMSNotSentException
     * @throws Throwable
     *
     * @return void
     */
    public function send(string $phone, int|string $message): void
    {
        $body = $this->setQueryParams($phone, $message)->sendRequest()->body();

        $this->fetchResponse($body)->addLogIfEnabled($phone, $message);

        $this->throwExceptionOnFailure();
    }

    /**
     * Log SMS to the database if logging is enabled.
     *
     * @param string $phone
     * @param int|string $message
     *
     * @return void
     */
    private function addLogIfEnabled(string $phone, int|string $message): void
    {
        if ($this->logging !== true) {
            return;
        }

        MSMLog::query()->create([
            'phone' => $phone,
            'message' => $message,
            'response_code' => $this->code,
            'response_text' => $this->text,
        ]);
    }

    /**
     * Fetch the code and the message from the response.
     *
     * @param string $body
     *
     * @return $this
     */
    private function fetchResponse(string $body): self
    {
        parse_str($body, $response);

        $this->code = $response['errno'] ?? null;
        $this->text = $response['errtext'] ?? null;

        return $this;
    }

    /**
     * Send request to SMS service.
     *
     * @return Response
     */
    private function sendRequest(): Response
    {
        return Http::get(self::URL, $this->query);
    }

    /**
     * Set query parameters for request.
     *
     * @param string $phone
     * @param int|string $message
     *
     * @return $this
     */
    private function setQueryParams(string $phone, int|string $message): self
    {
        $this->query = [
            'user' => $this->username,
            'password' => $this->password,
            'from' => $this->sender,
            'gsm' => $phone,
            'text' => $message,
        ];

        return $this;
    }

    /**
     * Throw an exception if SMS is not sent.
     *
     * @throws SMSNotSentException
     * @throws Throwable
     *
     * @return void
     */
    private function throwExceptionOnFailure(): void
    {
        if ($this->code == 100) {
            return;
        }

        throw new SMSNotSentException($this->text);
    }
}
