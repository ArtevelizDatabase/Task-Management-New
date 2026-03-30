<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * When the client sends X-Requested-With: XMLHttpRequest and the controller
 * returns a redirect (typical flash + redirect pattern), return JSON instead
 * so the browser can navigate via JS without a full round-trip POST redirect.
 */
class AjaxRedirectFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! $request->isAJAX()) {
            return $response;
        }

        if (! $response instanceof RedirectResponse) {
            return $response;
        }

        $location = $response->getHeaderLine('Location');
        if ($location === '') {
            return $response;
        }

        return service('response')
            ->setStatusCode(200)
            ->setJSON([
                'success'  => true,
                'redirect' => $location,
                'csrf'     => csrf_hash(),
            ]);
    }
}
