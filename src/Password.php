<?php

namespace Bredala\Utils;

/**
 * Description of Password
 */
class Password
{
    private ?string $salt = null;
    private ?int $cost = null;

    // -------------------------------------------------------------------------

    /**
     * Sets a salt for password hash
     *
     * @param string $salt
     * @return $this
     */
    public function setSalt(?string $salt): Password
    {
        $this->salt = $salt ?: null;
        return $this;
    }

    /**
     * Set a cost for password hash
     *
     * @param integer $cost
     * @return $this
     */
    public function setCost(?int $cost): Password
    {
        $this->cost = $cost ?: null;
        return $this;
    }

    // -------------------------------------------------------------------------

    /**
     * Return a password hash
     *
     * @param string $password
     * @return string
     */
    public function hash(string $password): string
    {
        $options = [];

        if (isset($this->salt)) {
            $options['salt'] = $this->salt;
        }

        if (isset($this->cost)) {
            $options['cost'] = $this->cost;
        }

        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    // -------------------------------------------------------------------------

    /**
     * Verify if a password and a hash corresponds
     *
     * @param string $password
     * @param string $hash
     * @return boolean
     */
    public function verify(string $password, string $hash): bool
    {
        if (!$password || !$hash) {
            return false;
        }

        return password_verify($password, $hash);
    }

    // -------------------------------------------------------------------------
}
