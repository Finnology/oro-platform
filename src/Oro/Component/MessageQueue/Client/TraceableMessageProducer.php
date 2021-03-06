<?php
namespace Oro\Component\MessageQueue\Client;

class TraceableMessageProducer implements MessageProducerInterface
{
    /**
     * @var MessageProducerInterface
     */
    private $messageProducer;

    /**
     * @var array
     */
    protected $traces = [];

    /**
     * @param MessageProducerInterface $messageProducer
     */
    public function __construct(MessageProducerInterface $messageProducer)
    {
        $this->messageProducer = $messageProducer;
    }

    /**
     * {@inheritdoc}
     */
    public function createMessage($body)
    {
        return $this->messageProducer->createMessage($body);
    }

    /**
     * {@inheritdoc}
     */
    public function send($topic, $message, $priority = MessagePriority::NORMAL)
    {
        $this->messageProducer->send($topic, $message, $priority);

        $this->traces[] = ['topic' => $topic, 'message' => $message, 'priority' => $priority];
    }

    /**
     * @return array
     */
    public function getTraces()
    {
        return $this->traces;
    }

    public function clearTraces()
    {
        $this->traces = [];
    }
}
