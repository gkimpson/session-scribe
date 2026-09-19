import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\RecordingController::store
* @see app/Http/Controllers/RecordingController.php:14
* @route '/recordings'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/recordings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RecordingController::store
* @see app/Http/Controllers/RecordingController.php:14
* @route '/recordings'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RecordingController::store
* @see app/Http/Controllers/RecordingController.php:14
* @route '/recordings'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RecordingController::store
* @see app/Http/Controllers/RecordingController.php:14
* @route '/recordings'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RecordingController::store
* @see app/Http/Controllers/RecordingController.php:14
* @route '/recordings'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\RecordingController::show
* @see app/Http/Controllers/RecordingController.php:44
* @route '/recordings/{recording}'
*/
export const show = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/recordings/{recording}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RecordingController::show
* @see app/Http/Controllers/RecordingController.php:44
* @route '/recordings/{recording}'
*/
show.url = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{recording}', parsedArgs.recording.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RecordingController::show
* @see app/Http/Controllers/RecordingController.php:44
* @route '/recordings/{recording}'
*/
show.get = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecordingController::show
* @see app/Http/Controllers/RecordingController.php:44
* @route '/recordings/{recording}'
*/
show.head = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\RecordingController::show
* @see app/Http/Controllers/RecordingController.php:44
* @route '/recordings/{recording}'
*/
const showForm = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecordingController::show
* @see app/Http/Controllers/RecordingController.php:44
* @route '/recordings/{recording}'
*/
showForm.get = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RecordingController::show
* @see app/Http/Controllers/RecordingController.php:44
* @route '/recordings/{recording}'
*/
showForm.head = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

/**
* @see \App\Http\Controllers\RecordingUploadController::__invoke
* @see app/Http/Controllers/RecordingUploadController.php:13
* @route '/recordings/{recording}/upload'
*/
export const upload = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(args, options),
    method: 'post',
})

upload.definition = {
    methods: ["post"],
    url: '/recordings/{recording}/upload',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RecordingUploadController::__invoke
* @see app/Http/Controllers/RecordingUploadController.php:13
* @route '/recordings/{recording}/upload'
*/
upload.url = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return upload.definition.url
            .replace('{recording}', parsedArgs.recording.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RecordingUploadController::__invoke
* @see app/Http/Controllers/RecordingUploadController.php:13
* @route '/recordings/{recording}/upload'
*/
upload.post = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RecordingUploadController::__invoke
* @see app/Http/Controllers/RecordingUploadController.php:13
* @route '/recordings/{recording}/upload'
*/
const uploadForm = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: upload.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\RecordingUploadController::__invoke
* @see app/Http/Controllers/RecordingUploadController.php:13
* @route '/recordings/{recording}/upload'
*/
uploadForm.post = (args: { recording: string | { id: string } } | [recording: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: upload.url(args, options),
    method: 'post',
})

upload.form = uploadForm

const recordings = {
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    upload: Object.assign(upload, upload),
}

export default recordings