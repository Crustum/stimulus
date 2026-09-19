<?php
declare(strict_types=1);

namespace TestApp\Controller\Admin;

use Cake\Controller\Controller;
use Cake\Http\Response;

/**
 * Simulates an admin controller that loads a named component alias.
 *
 * Each controller in the Admin namespace loads a hypothetical component
 * under a unique alias, reproducing the host app's pattern where
 * `CakeDC/Admin.AdminExt` is loaded with different aliases per controller.
 */
class AlphaController extends Controller
{
    /**
     * Initialization hook.
     *
     * Loads a component under the alias `Alpha` to prove per-controller
     * component isolation in the worker.
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
            ->withStringBody(json_encode(['controller' => 'Alpha']));
    }
}
