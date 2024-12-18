<?php

namespace Oro\Bundle\SoapBundle\Client\Settings;

class SoapClientSettings implements SoapClientSettingsInterface
{
    const OPTION_TIMEOUT = 'connection_timeout';

    /**
     * @var string|null
     */
    private $wsdlFilePath;

    /**
     * @var string
     */
    private $methodName;

    /**
     * @var array
     */
    private $soapOptions;

    /**
     * @param string|null $wsdlFilePath
     * @param string      $methodName
     * @param array       $soapOptions
     */
    public function __construct($wsdlFilePath, string $methodName, array $soapOptions = [])
    {
        $this->wsdlFilePath = $wsdlFilePath;
        $this->methodName = $methodName;
        $this->soapOptions = $soapOptions;
    }

    #[\Override]
    public function getWsdlFilePath()
    {
        return $this->wsdlFilePath;
    }

    #[\Override]
    public function getMethodName(): string
    {
        return $this->methodName;
    }

    #[\Override]
    public function getSoapOptions(): array
    {
        return $this->soapOptions;
    }
}
