<?php

namespace Oro\Bundle\SecurityBundle\Csrf;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

/**
 * Cookie based storage for CSRF tokens.
 */
class CookieTokenStorage implements TokenStorageInterface
{
    const CSRF_COOKIE_ATTRIBUTE = '_csrf_cookie';

    /** @var mixed true, false, 'auto' */
    private $secure;

    /** @var bool */
    private $httpOnly;

    /** @var RequestStack */
    private $requestStack;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @deprecated
     * @param $secure
     */
    public function setSecure($secure)
    {
        $this->secure = $secure;
    }

    /**
     * @deprecated
     * @param bool $httpOnly
     */
    public function setHttpOnly(bool $httpOnly)
    {
        $this->httpOnly = $httpOnly;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken($tokenId)
    {
        return $this->getCookieValue($tokenId);
    }

    /**
     * {@inheritdoc}
     */
    public function setToken($tokenId, $token)
    {
        $request = $this->getRequest();
        if (!$request) {
            throw new \RuntimeException('Cookie Token Storage may be used only in request scope');
        }

        $request->attributes->set(
            self::CSRF_COOKIE_ATTRIBUTE,
            $this->createCookie($tokenId, $token)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function removeToken($tokenId)
    {
        $this->setToken($tokenId, '');
    }

    /**
     * {@inheritdoc}
     */
    public function hasToken($tokenId)
    {
        return $this->getCookieValue($tokenId) !== '';
    }

    /**
     * @param string $tokenId
     * @param string $tokenValue
     *
     * @return Cookie
     */
    private function createCookie($tokenId, $tokenValue): Cookie
    {
        return new Cookie(
            $tokenId,
            $tokenValue,
            0,
            '/',
            null,
            'auto' === $this->secure ? null : $this->secure,
            $this->httpOnly
        );
    }

    /**
     * @return string
     */
    private function getCookieValue($tokenId)
    {
        return $this->getRequest() ? $this->getRequest()->cookies->get($tokenId, '') : '';
    }

    /**
     * @return null|Request
     */
    private function getRequest()
    {
        return $this->requestStack->getCurrentRequest();
    }
}
