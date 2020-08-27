<?php

namespace atk4\Auth0\Model;

use atk4\data\Model;

/**
 * Auth0 User Model used to store data from Auth0 UserData.
 */
class Auth0User extends Model
{
    public $caption = 'User';

    public $id_field = 'email';

    public function init(): void
    {
        parent::init();

        $this->addField('given_name');
        $this->addField('family_name');
        $this->addField('nickname');
        $this->addField('picture');
        $this->addField('locale');
        $this->addField('updated_at');
        $this->addField('email_verified');
    }
}