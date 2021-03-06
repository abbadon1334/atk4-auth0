<?php


namespace atk4\Auth0;

use atk4\Auth0\Model\Auth0User;
use atk4\core\AppScopeTrait;
use atk4\core\ContainerTrait;
use atk4\core\DIContainerTrait;
use atk4\core\HookTrait;
use atk4\core\InitializerTrait;
use atk4\core\TrackableTrait;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\ui\App;
use atk4\ui\Callback;
use atk4\ui\CallbackLater;
use atk4\ui\Exception;
use Auth0\SDK\Auth0 as SDKAuth0;
use Auth0\SDK\Exception\ApiException;
use Auth0\SDK\Exception\CoreException;

class Auth0
{
    use InitializerTrait {
        init as _init;
    }
    use AppScopeTrait;
    use DIContainerTrait;
    use HookTrait;
    use ContainerTrait;
    use TrackableTrait;

    /**
     * Official SDK Auth0
     *
     * @var SDKAuth0
     */
    private $auth0;

    /**
     * Auth0 config
     *
     * @var array
     */
    private $config;

    /**
     * Model used to store Auth0 user data
     *
     * @var Auth0User
     */
    private $model;

    /** @var Auth0FieldsMapper */
    private $fields_mapper;

    /**
     * Auth0 constructor.
     *
     * @param array $defaults
     *
     * @throws Exception
     */
    public function __construct(array $defaults)
    {
        $this->setDefaults($defaults);
        $this->validateConfiguration();

        $this->short_name = 'auth0';
    }

    /**
     * Validate Auth0 configuration
     *
     * @throws Exception
     */
    private function validateConfiguration(): void
    {
        if (empty($this->config)) {
            $exc = new Exception(['definition of auth0_config is needed']);
            $exc->addSolution('you need to define the array of configuration for Auth0 service');
            throw $exc;
        }

        if (!is_a($this->model, Model::class, true)) {
            throw new Exception(['definition of app_user_model is needed and must be of type ' . Model::class]);
        }

        if (!is_a($this->fields_mapper, Auth0FieldsMapper::class, true)) {
            throw new Exception(['definition of auth0_fields_mapper is needed and be of type ' . Auth0FieldsMapper::class]);
        }
    }

    public function init(): void
    {
        $this->_init();

        $this->addAppMethods();

        $this->addCallbackLogin();

        $this->auth0Connect();
    }

    /**
     * Add Dynamic methods to App
     *
     * @throws \atk4\core\Exception
     */
    private function addAppMethods(): void
    {
        $this->app->addMethod('getAuth0', function (App $app) {
            return $this;
        });
    }

    /**
     * Add CallbackLater for login to Auth0
     *
     * @throws Exception
     * @throws \atk4\core\Exception
     */
    private function addCallbackLogin(): void
    {
        /** @var Callback $callback */
        $callback = $this->app->add([
            CallbackLater::class,
            'short_name' => 'auth0_cb',
        ]);

        $callback->set(function () {

            if (!$this->isLogged()) {
                throw new Exception(['there was an error logging in']);
            }

            if (null !== $this->app->hook('onAfterUserLogin', [$this->model])) {
                $this->app->redirect($this->config['returnTo']); // clear url
            }
        });

        if (strpos($this->config['redirect_uri'], $callback->getURL()) === false) {
            throw new Exception('you need to add "' . $callback->getURL() . '" at the end of your redirect_uri configuration');
        }
    }

    /**
     * Return if the user is logged
     *
     * @return bool
     */
    public function isLogged(): bool
    {
        return $this->getUser()->loaded();
    }

    /**
     * Return the current logged user
     *
     * @return Model
     */
    public function getUser(): Model
    {
        return $this->model;
    }

    /**
     * Check if session has token and load the app_user, if not it will call the Auth0 login.
     *
     * @throws Exception
     * @throws Exception\ExitApplicationException
     * @throws ApiException
     * @throws CoreException
     * @throws \atk4\core\Exception
     * @throws \atk4\data\Exception
     */
    private function auth0Connect(): void
    {
        $this->auth0 = new SDKAuth0($this->config);

        $user_data = $this->auth0->getUser();

        if (null === $user_data) {

            if (($_GET['code'] ?? null) !== null) {
                throw new Exception('There was an error on Login');
            }

            $this->login();
        }

        $this->auth0_user_model = new Auth0User(new Persistence\Static_([$user_data]));
        $this->auth0_user_model->tryLoadAny();

        $this->mapApplicationUserModel();
    }

    /**
     * Call Auth0 Login
     *
     */
    private function login(): void
    {
        $this->app->hook('onBeforeUserLogin', []);

        // redirect to auth0 login
        $this->app->redirect($this->auth0->getLoginUrl());
    }

    /**
     * Populate the date using the Mapped fields Auth0->UserModel
     *
     * @throws \atk4\core\Exception
     * @throws \atk4\data\Exception
     */
    private function mapApplicationUserModel(): void
    {
        $this->model->tryLoadBy(
            $this->fields_mapper->getMappedField('email'),
            $this->auth0_user_model->get('email')
        );

        foreach ($this->auth0_user_model->getFields() as $fieldNameAuth0 => $fieldNameUser) {

            $field = $this->fields_mapper->getMappedField($fieldNameAuth0);

            if ($field) {
                $this->model->set($field, $this->auth0_user_model->get($fieldNameAuth0));
            }
        }

        $this->model->save();
    }

    /**
     * Call Logout, clear local session and call Auth0 logout url to remote logout.
     *
     * @throws Exception\ExitApplicationException
     * @throws \atk4\core\Exception
     */
    public function logout()
    {
        $this->app->hook('onBeforeUserLogout', [$this->model]);

        $this->auth0->logout();

        $this->app->hook('onAfterUserLogout', []);

        // remote Auth0 logout
        $logout_url = sprintf('http://%s/v2/logout?client_id=%s&returnTo=%s', $this->config['domain'],
            $this->config['client_id'], $this->config['returnTo']);
        $this->app->redirect($logout_url);
    }
}