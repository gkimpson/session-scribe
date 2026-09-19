import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RecordingIndexController::__invoke
* @see app/Http/Controllers/RecordingIndexController.php:18
* @route '/recordings'
*/
const RecordingIndexController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RecordingIndexController.url(options),
    method: 'get',
})

RecordingIndexController.definition = {
    methods: ["get","head"],
    url: '/recordings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RecordingIndexController::__invoke
* @see app/Http/Controllers/RecordingIndexController.php:18
* @route '/recordings'
*/
RecordingIndexController.url = (options?: RouteQueryOptions) => {
    return RecordingIndexController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RecordingIndexController::__invoke
* @see app/Http/Controllers/RecordingIndexController.php:18
* @route '/recordings'
*/
RecordingIndexController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RecordingIndexController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecordingIndexController::__invoke
* @see app/Http/Controllers/RecordingIndexController.php:18
* @route '/recordings'
*/
RecordingIndexController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: RecordingIndexController.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\RecordingIndexController::__invoke
* @see app/Http/Controllers/RecordingIndexController.php:18
* @route '/recordings'
*/
const RecordingIndexControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: RecordingIndexController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecordingIndexController::__invoke
* @see app/Http/Controllers/RecordingIndexController.php:18
* @route '/recordings'
*/
RecordingIndexControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: RecordingIndexController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecordingIndexController::__invoke
* @see app/Http/Controllers/RecordingIndexController.php:18
* @route '/recordings'
*/
RecordingIndexControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: RecordingIndexController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

RecordingIndexController.form = RecordingIndexControllerForm

export default RecordingIndexController