import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../wayfinder'
/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
export const home = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(args, options),
    method: 'get',
})

home.definition = {
    methods: ["get","head"],
    url: '/{transcriptId?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
home.url = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { transcriptId: args }
    }

    if (Array.isArray(args)) {
        args = {
            transcriptId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
        "transcriptId",
    ])

    const parsedArgs = {
        transcriptId: args?.transcriptId,
    }

    return home.definition.url
            .replace('{transcriptId?}', parsedArgs.transcriptId?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
home.get = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
home.head = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
const homeForm = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: home.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
homeForm.get = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: home.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
homeForm.head = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: home.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

home.form = homeForm
