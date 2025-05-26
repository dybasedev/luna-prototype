<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler;

use Ckr\Util\ArrayMerger;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * @mixin Model
 * @property Handler $handler
 * @property array $config
 */
trait WithModelHandler
{
    /**
     * @throws BindingResolutionException
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(luna_module_configure(LunaHandlerConfigure::class)->model, 'handler_id', 'id');
    }

    /**
     * @throws BindingResolutionException
     */
    public function handlerInstance(): BaseHandler
    {
        /** @var BaseHandler $handler */
        $handler = app()->make($this->handler->handler);

        if ($handler instanceof ModelHandler) {
            $handler->loadInstance($this);
        }

        return $handler->withConfig(ArrayMerger::doMerge($this->handler->config, $this->config));
    }
}
