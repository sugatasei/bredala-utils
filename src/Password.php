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
    public function setSalt(string $salt): Password
    {
        if ($salt) {
            $this->salt = $salt;
        }

        return $this;
    }

    /**
     * Set a cost for password hash
     *
     * @param integer $cost
     * @return $this
     */
    public function setCost(int $cost): Password
    {
        if ($cost) {
            $this->cost = $cost;
        }

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
        return password_verify($password, $hash);
    }

    // -------------------------------------------------------------------------
}

/* End of file */
