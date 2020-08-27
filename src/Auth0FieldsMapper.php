<?php


namespace atk4\Auth0;


use atk4\core\Exception;

class Auth0FieldsMapper
{
    private $fields = [
        'given_name'     => null,
        'family_name'    => null,
        'nickname'       => null,
        'picture'        => null,
        'locale'         => null,
        'updated_at'     => null,
        'email'          => null,
        'email_verified' => null,
    ];

    /**
     * Map an Auth0 field to UserModel Field
     *
     * @param string $auth0_field
     * @param string $atk_field
     *
     * @throws Exception
     * @return $this
     */
    public function setField(string $auth0_field, string $atk_field): self
    {
        if (!array_key_exists($auth0_field, $this->fields)) {
            throw new Exception([$auth0_field . ' is not a normalized Auth0 field']);
        }

        $this->fields[$auth0_field] = $atk_field;

        return $this;
    }

    /**
     * Return a mapped Field by name
     *
     * @param $auth0_field
     *
     * @return string|null
     */
    public function getMappedField($auth0_field): ?string
    {
        return $this->fields[$auth0_field] ?? null;
    }
}