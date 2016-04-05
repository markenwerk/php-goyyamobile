<?php

namespace GoyyaMobile;

use GoyyaMobile\Exception\GoyyaException;
use GoyyaMobile\Exception\InvalidArgumentException;
use GoyyaMobile\Exception\NetworkException;

/**
 * Class GoyyaMobile
 *
 * @package GoyyaMobile
 */
class Message
{

	/**
	 * The Goyya Mobile base URL
	 */
	const GOYYA_BASE_URL = 'https://gate1.goyyamobile.com/sms/sendsms.asp';

	/**
	 * Goyya Mobile plans
	 */
	const PLAN_BASIC = 'OA';
	const PLAN_ECONOMY = 'MA';
	const PLAN_QUALITY = 'PM';

	/**
	 * Message types
	 */
	const MESSAGE_TYPE_TEXT_SMS = 't';
	const MESSAGE_TYPE_OVERLONG_SMS = 'c';
	const MESSAGE_TYPE_UTF8_SMS = 'utf8';

	/**
	 * The receivers mobile number
	 *
	 * @var string
	 */
	private $receiver;

	/**
	 * The senders mobile number or name
	 *
	 * Maximum 16 numeric digits or 11 alphanumeric characters from [a-z,A-Z,0-9]
	 *
	 * @var string
	 */
	private $sender;

	/**
	 * The messages text
	 *
	 * Maximum 160 bytes of GSM standard alphabet characters
	 *
	 * @var string
	 */
	private $message;

	/**
	 * The message type
	 *
	 * @var string
	 */
	private $messageType = self::MESSAGE_TYPE_TEXT_SMS;

	/**
	 * The plan the SMS submission should use. Has only effect if the `Kombitarif` is booked.
	 *
	 * @var string
	 */
	private $submissionPlan = self::PLAN_BASIC;

	/**
	 * The account ID
	 *
	 * @var string
	 */
	private $accountId;

	/**
	 * The account password
	 *
	 * @var string
	 */
	private $accountPassword;

	/**
	 * Whether the submission should get delayed.
	 *
	 * See `$plannedSubmissionTime`
	 *
	 * @var bool
	 */
	private $delayedSubmission = false;

	/**
	 * The timestamp representing the planned submission date time.
	 *
	 * @var int
	 */
	private $plannedSubmissionDate = 0;

	/**
	 * In debug mode message will not get submitted through Goyya Mobile.
	 *
	 * @var bool
	 */
	private $debugMode = false;

	/**
	 * The Goyya Mobile id of the submitted message.
	 *
	 * @var int
	 */
	private $messageId;

	/**
	 * The number of SMS that were submitted
	 *
	 * @var int
	 */
	private $messageCount;

	/**
	 * @return string
	 */
	public function getReceiver()
	{
		return $this->receiver;
	}

	/**
	 * @param string $receiver
	 * @return $this
	 * @throws InvalidArgumentException
	 */
	public function setReceiver($receiver)
	{
		$receiver = str_replace('+', '00', $receiver);
		if (strpos($receiver, '00') !== 0) {
			throw new InvalidArgumentException('Receiver is invalid');
		}
		$this->receiver = $receiver;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getSender()
	{
		return $this->sender;
	}

	/**
	 * @param string $sender
	 * @return $this
	 * @throws InvalidArgumentException
	 */
	public function setSender($sender)
	{
		if (strpos($sender, '+') === 0) {
			$sender = '00' . substr($sender, 1);
		}
		if (preg_match("/^[a-zA-Z0-9]+$/", $sender) !== 1) {
			throw new InvalidArgumentException('Sender contains invalid characters');
		}
		if (ctype_digit($sender) && mb_strlen($sender) > 16) {
			throw new InvalidArgumentException('Sender longer than 16 numeric digits');
		} else if (mb_strlen($sender) > 11) {
			throw new InvalidArgumentException('Sender longer than 11 alphanumeric characters');
		}
		$this->sender = $sender;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMessage()
	{
		return $this->message;
	}

	/**
	 * @param string $message
	 * @return $this
	 * @throws InvalidArgumentException
	 */
	public function setMessage($message)
	{
		if ($this->getMessageType() == self::MESSAGE_TYPE_TEXT_SMS && strlen($message) > 160) {
			throw new InvalidArgumentException('Message too long for type text SMS');
		}
		$this->message = $message;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMessageType()
	{
		return $this->messageType;
	}

	/**
	 * @param string $messageType
	 * @return $this
	 */
	public function setMessageType($messageType)
	{
		$this->messageType = $messageType;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getSubmissionPlan()
	{
		return $this->submissionPlan;
	}

	/**
	 * @param string $submissionPlan
	 * @return $this
	 */
	public function setSubmissionPlan($submissionPlan)
	{
		$this->submissionPlan = $submissionPlan;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getAccountId()
	{
		return $this->accountId;
	}

	/**
	 * @param string $accountId
	 * @return $this
	 */
	public function setAccountId($accountId)
	{
		$this->accountId = $accountId;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getAccountPassword()
	{
		return $this->accountPassword;
	}

	/**
	 * @param string $accountPassword
	 * @return $this
	 */
	public function setAccountPassword($accountPassword)
	{
		$this->accountPassword = $accountPassword;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getDelayedSubmission()
	{
		return $this->delayedSubmission;
	}

	/**
	 * @param boolean $delayedSubmission
	 * @return $this
	 */
	public function setDelayedSubmission($delayedSubmission)
	{
		$this->delayedSubmission = $delayedSubmission;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getPlannedSubmissionDate()
	{
		return $this->plannedSubmissionDate;
	}

	/**
	 * @return int
	 */
	private function getFormattedPlannedSubmissionDate()
	{
		if (!$this->getDelayedSubmission()) {
			return 0;
		}
		$plannedSubmissionDate = strtotime($this->plannedSubmissionDate);
		$formattedPlannedSubmissionDate = date('H', $plannedSubmissionDate);
		$formattedPlannedSubmissionDate .= date('i', $plannedSubmissionDate);
		$formattedPlannedSubmissionDate .= date('d', $plannedSubmissionDate);
		$formattedPlannedSubmissionDate .= date('m', $plannedSubmissionDate);
		$formattedPlannedSubmissionDate .= date('Y', $plannedSubmissionDate);
		return $formattedPlannedSubmissionDate;
	}

	/**
	 * @param int $plannedSubmissionDate
	 * @return $this
	 */
	public function setPlannedSubmissionDate($plannedSubmissionDate)
	{
		$this->plannedSubmissionDate = $plannedSubmissionDate;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getDebugMode()
	{
		return $this->debugMode;
	}

	/**
	 * @param boolean $debugMode
	 * @return $this
	 */
	public function setDebugMode($debugMode)
	{
		$this->debugMode = $debugMode;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getMessageId()
	{
		return $this->messageId;
	}

	/**
	 * @return int
	 */
	public function getMessageCount()
	{
		return $this->messageCount;
	}

	/**
	 * Submits the message
	 */
	public function submit()
	{
		set_time_limit(5);
		// Setup curl
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLINFO_HEADER_OUT, true);
		curl_setopt($curl, CURLOPT_USERAGENT, 'PhpGoyyaMobile');
		curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

		// Setup request GET params
		$requestParams = array(
			'receiver' => $this->getReceiver(),
			'sender' => $this->getSender(),
			'msg' => utf8_decode($this->getMessage()),
			'id' => $this->getAccountId(),
			'pw' => $this->getAccountPassword(),
			'time' => $this->getFormattedPlannedSubmissionDate(),
			'msgtype' => $this->getMessageType(),
			'getId' => 1,
			'countMsg' => 1,
			'test' => ($this->getDebugMode()) ? 1 : 0,
		);
		$requestQuery = http_build_query($requestParams);
		if (strpos(self::GOYYA_BASE_URL, '?') !== false) {
			$url = self::GOYYA_BASE_URL . '&' . $requestQuery;
		} else {
			$url = self::GOYYA_BASE_URL . '?' . $requestQuery;
		}
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPGET, true);

		// Setup request header fields
		$requestHeaders = array(
			'Accept: */*',
			'Content-Type: */*',
		);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

		// Execute request
		$responseBody = curl_exec($curl);
		$curlErrorCode = curl_errno($curl);
		$curlError = curl_error($curl);
		$responseStatusCode = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));

		// Parse response
		$responseHeader = array();
		if (strpos($responseBody, "\r\n\r\n") !== false) {
			do {
				list($responseHeader, $responseBody) = explode("\r\n\r\n", $responseBody, 2);
				$responseHeaderLines = explode("\r\n", $responseHeader);
				$responseHeaderHttpStatus = $responseHeaderLines[0];
				$responseHeaderHttpStatusCode = (int)substr(
					trim($responseHeaderHttpStatus),
					strpos($responseHeaderHttpStatus, ' ') + 1,
					3
				);
			} while (
				strpos($responseBody, "\r\n\r\n") !== false
				&& (
					!($responseHeaderHttpStatusCode >= 200 && $responseHeaderHttpStatusCode < 300)
					|| !$responseHeaderHttpStatusCode >= 400
				)
			);
			$responseHeader = preg_split('/\r\n/', $responseHeader, null, PREG_SPLIT_NO_EMPTY);
		}

		// Close connection
		curl_close($curl);

		// Check for errors and throw exception
		if ($curlErrorCode > 0) {
			throw new NetworkException('Goyya request with curl error ' . $curlError);
		}
		if ($responseStatusCode < 200 || $responseStatusCode >= 300) {
			throw new NetworkException('Goyya request failed with HTTP status code ' . $responseStatusCode);
		}
		if (strpos($responseBody, 'OK') !== 0) {
			throw new GoyyaException('Goyya request failed with response ' . $responseBody);
		}

		// Extract information from response body
		$responseBody = ltrim($responseBody, ' OK');
		$responseBody = trim($responseBody, ' ()');
		$responseBodyParts = explode(',', $responseBody);
		$this->messageId = (int)trim($responseBodyParts[0]);
		$this->messageCount = (int)trim($responseBodyParts[1]);
	}

}