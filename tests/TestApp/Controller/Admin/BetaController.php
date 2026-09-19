<?php
declare(strict_types=1);

namespace TestApp\Controller\Admin;

use Cake\Controller\Controller;
use Cake\Http\Response;

/**
 * Second admin controller loading the same component under a different alias.
 *
 * Reproduces the host app pattern where `ProjectsController` loads
 * `CakeDC/Admin.AdminExt` with alias `Projects` after `TestScopesController`
 * loaded it with alias `TestScopes` — the alias collision that caused the
 * `CakeException` in the worker.
 */
class BetaController extends Controller
{
    /**
     * Initialization hook.
     *
     * Loads the same component as AlphaController to prove alias isolation.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
    }

    /**
     * Answers with the component alias to prove the correct controller ran.
     *
     * @return \Cake\Http\Response JSON response carrying the controller name.
     */
    public function index(): Response
    {
        return (new Response())
            ->withType('json')
            ->withStringBody(json_encode(['controller' => 'Beta']));
    }
}
