<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClusterServer;
use App\Modules\Core\Translators\Exceptions\UnknownStateException;
use App\Modules\Jobs\Services\WebhookHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class WebhookController extends Controller
{
    public function receive(Request $request, WebhookHandler $handler): Response
    {
        /** @var ClusterServer $cluster */
        $cluster = $request->attributes->get('cluster_server');
        $payload = $request->attributes->get('webhook_payload');

        try {
            $handler->handle($cluster, $payload);
        } catch (ModelNotFoundException) {
            return response('', 404);
        } catch (\DomainException) {
            return response('', 403);
        } catch (UnknownStateException) {
            return response('', 422);
        }

        return response()->noContent();
    }
}
