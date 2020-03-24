<?php

require_once '../secret.php';

require_once "../../vendor/autoload.php";

use atk4\Auth0\Auth0;
use atk4\Auth0\Auth0FieldsMapper;
use atk4\data\Model;
use atk4\schema\Migration;
use atk4\ui\App;
use atk4\ui\Layout\Centered;

class ApplicationUser extends Model
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }

    public $table = 'user';

    public function init()
    {
        $this->_init();

        $this->addField('email');
    }
}

$db = new atk4\data\Persistence\SQL('sqlite::memory:');
Migration::getMigration(new ApplicationUser($db))->migrate();

$app = new App([
    'title' => 'Auth0 test',
    'always_run' => false,
    'call_exit' => false
]);

$app->initLayout(Centered::class);

$app->addHook('onBeforeUserLogin', function(App $app) {
});

$app->addHook('onAfterUserLogin', function(App $app, ApplicationUser $user) {
    $app->redirect('/'); // clear url
});

$app->addHook('onBeforeUserLogout', function(App $app, ApplicationUser $user) {
});

$app->addHook('onAfterUserLogout', function(App $app) {
});

$app->add([
    Auth0::class,
    [
        'auth0_config' => [
            'domain' => AUTH0_DOMAIN,
            'client_id' => AUTH0_CLIENT_ID,
            'client_secret' => AUTH0_CLIENT_SECRET,
            'redirect_uri' => 'http://127.0.0.1/index.php?atk_centered_auth0_callback=callback&__atk_callback=1',
            'returnTo' => 'http://127.0.0.1/',
            'scope' => 'profile email',
            'persist_id_token' => true,
            //'persist_access_token' => true,
            'persist_refresh_token' => true,
            'debug' => true
        ],
        'app_user_model' => new ApplicationUser($db),
        'auth0_fields_mapper' => (new Auth0FieldsMapper())->setField('email', 'email')
    ]
]);

/** @var \atk4\ui\Callback $callback */
$callback = $app->add(['Callback']);
$callback->set(function(App $app) {
    $app->getAuth()->logout();
}, [$app]);

$app->add(['Button', 'logout ' . $app->getAuth()->getUser()['email']])->link($callback->getURL());

$app->run();