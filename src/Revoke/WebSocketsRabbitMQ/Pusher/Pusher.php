<?php
/**
 * Pusher.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        revoke:WebSocketsRabbitMQ!
 * @subpackage     Pusher
 * @since          1.0.0
 *
 * @date           01.03.17
 */

declare(strict_types = 1);

namespace Revoke\WebSocketsRabbitMQ\Pusher;

use Psr\Log;

use IPub;
use Revoke\WebSocketsRabbitMQ;

use IPub\WebSockets\Router;

use IPub\WebSocketsWAMP\PushMessages;
use IPub\WebSocketsWAMP\Serializers;

/**
 * ZeroMQ message pusher
 *
 * @package        revoke:WebSocketsRabbitMQ!
 * @subpackage     Pushers
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
final class Pusher extends PushMessages\Pusher
{
	/**
	 * @var WebSocketsRabbitMQ\Configuration
	 */
	private $configuration;

	/**
	 * @var Log\LoggerInterface
	 */
	private $logger;

	/**
	 * @var \ZMQSocket
	 */
	private $socket;

	/**
	 * @param WebSocketsRabbitMQ\Configuration $configuration
	 * @param Log\LoggerInterface|NULL $logger
	 * @param Router\LinkGenerator $linkGenerator
	 * @param Serializers\PushMessageSerializer $serializer
	 */
	public function __construct(
		WebSocketsRabbitMQ\Configuration $configuration,
		Log\LoggerInterface $logger = NULL,
		Router\LinkGenerator $linkGenerator,
		Serializers\PushMessageSerializer $serializer
	) {
		parent::__construct('zmq', $serializer, $linkGenerator);

		$this->configuration = $configuration;
		$this->logger = $logger === NULL ? new Log\NullLogger : $logger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function close() : void
	{
		if ($this->isConnected() === FALSE) {
			return;
		}

		$this->socket->disconnect($this->configuration->getHost() . ':' . $this->configuration->getPort());
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doPush(string $data, array $context = []) : void
	{
		if ($this->isConnected() === FALSE) {
			if (!extension_loaded('zmq')) {
				throw new \RuntimeException(sprintf(
					'%s pusher require %s php extension',
					get_class($this),
					$this->getName()
				));
			}

			$context = new \ZMQContext(1, $this->configuration->isPersistent());

			$this->socket = new \ZMQSocket($context, \ZMQ::SOCKET_PUSH);
			$this->socket->connect($this->configuration->getProtocol() . '://' . $this->configuration->getHost() . ':' . $this->configuration->getPort());

			$this->setConnected();
		}

		$this->socket->send($data);
	}
}
