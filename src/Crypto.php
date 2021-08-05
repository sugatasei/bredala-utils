<?php

namespace Bredala\Utils;

/**
 * Crypto
 */
class Crypto
{

    const METHOD = 'aes-256-cbc';

    private string $method;

    // -------------------------------------------------------------------------

    public function __construct(?string $method = null)
    {
        $this->method = $method ?: self::METHOD;
    }

    // -------------------------------------------------------------------------

    /**
     * @return $this
     */
    public static function make(?string $method = null): Crypto
    {
        return new static($method);
    }

    /**
     * @param string $str
     * @param string $key
     * @return string
     */
    public function encode(string $str, string $key): string
    {
        $iv  = $this->iv($key);
        $ssl = openssl_encrypt($str, $this->method, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($ssl);
    }

    /**
     * @param string $str
     * @param string $key
     * @return mixed
     */
    public function decode(string $str,  string $key): string
    {
        if (preg_match('/[^a-zA-Z0-9\/\+=]/', $str)) {
            return false;
        }

        $iv  = $this->iv($key);
        $dec = base64_decode($str);
        return openssl_decrypt($dec, $this->method, $key, OPENSSL_RAW_DATA, $iv) ?: '';
    }

    /**
     * Création du vecteur d'initialisation à partir de la clé
     *
     * @param string $key
     * @return string
     */
    private function iv(string $key): string
    {
        $iv_len = openssl_cipher_iv_length($this->method);
        return mb_substr(str_pad($key, $iv_len, '0'), 0, $iv_len);
    }

    // -------------------------------------------------------------------------
}

/* End of file */
