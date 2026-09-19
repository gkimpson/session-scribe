import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RecordingUploadController::__invoke
* @see app/Http/Controllers/RecordingUploadController.php:13
* @route '/recordings/{recording}/upload'
*/
const RecordingUploadController = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: RecordingUploadController.url(args, options),
    method: 'post',
})

RecordingUploadController.definition = {
    methods: ["post"],
    url: '/recordings/{recording}/upload',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RecordingUploadController::__invoke
* @see app/Http/Controllers/RecordingUploadController.php:13
* @route '/recordings/{recording}/upload'
*/
RecordingUploadController.url = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { recording: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { recording: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            recording: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        recording: typeof args.recording === 'object'
        ? args.recording.id
        : args.recording,
    }

    return RecordingUploadController.definition.url
            .replace('{recording}', parsedArgs.recording.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RecordingUploadController::__invoke
* @see app/Http/Controllers/RecordingUploadController.php:13
* @route '/recordings/{recording}/upload'
*/
RecordingUploadController.post = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: RecordingUploadController.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RecordingUploadController::__invoke
* @see app/Http/Controllers/RecordingUploadController.php:13
* @route '/recordings/{recording}/upload'
*/
const RecordingUploadControllerForm = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: RecordingUploadController.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RecordingUploadController::__invoke
* @see app/Http/Controllers/RecordingUploadController.php:13
* @route '/recordings/{recording}/upload'
*/
RecordingUploadControllerForm.post = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: RecordingUploadController.url(args, options),
    method: 'post',
})

RecordingUploadController.form = RecordingUploadControllerForm

export default RecordingUploadController