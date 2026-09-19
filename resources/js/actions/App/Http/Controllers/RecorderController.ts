import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
const RecorderController = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RecorderController.url(args, options),
    method: 'get',
})

RecorderController.definition = {
    methods: ["get","head"],
    url: '/{transcriptId?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
RecorderController.url = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return RecorderController.definition.url
            .replace('{transcriptId?}', parsedArgs.transcriptId?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
RecorderController.get = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RecorderController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
RecorderController.head = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: RecorderController.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
const RecorderControllerForm = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: RecorderController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
RecorderControllerForm.get = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: RecorderController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecorderController::__invoke
* @see app/Http/Controllers/RecorderController.php:13
* @route '/{transcriptId?}'
*/
RecorderControllerForm.head = (args?: { transcriptId?: string | number } | [transcriptId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: RecorderController.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

RecorderController.form = RecorderControllerForm

export default RecorderController