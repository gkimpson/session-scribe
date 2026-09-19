import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\TranscriptSummaryController::store
* @see app/Http/Controllers/TranscriptSummaryController.php:21
* @route '/transcripts/{transcript}/summaries'
*/
export const store = (args: { transcript: string | { uuid: string } } | [transcript: string | { uuid: string } ] | string | { uuid: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/transcripts/{transcript}/summaries',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\TranscriptSummaryController::store
* @see app/Http/Controllers/TranscriptSummaryController.php:21
* @route '/transcripts/{transcript}/summaries'
*/
store.url = (args: { transcript: string | { uuid: string } } | [transcript: string | { uuid: string } ] | string | { uuid: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { transcript: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'uuid' in args) {
        args = { transcript: args.uuid }
    }

    if (Array.isArray(args)) {
        args = {
            transcript: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        transcript: typeof args.transcript === 'object'
        ? args.transcript.uuid
        : args.transcript,
    }

    return store.definition.url
            .replace('{transcript}', parsedArgs.transcript.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TranscriptSummaryController::store
* @see app/Http/Controllers/TranscriptSummaryController.php:21
* @route '/transcripts/{transcript}/summaries'
*/
store.post = (args: { transcript: string | { uuid: string } } | [transcript: string | { uuid: string } ] | string | { uuid: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\TranscriptSummaryController::store
* @see app/Http/Controllers/TranscriptSummaryController.php:21
* @route '/transcripts/{transcript}/summaries'
*/
const storeForm = (args: { transcript: string | { uuid: string } } | [transcript: string | { uuid: string } ] | string | { uuid: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\TranscriptSummaryController::store
* @see app/Http/Controllers/TranscriptSummaryController.php:21
* @route '/transcripts/{transcript}/summaries'
*/
storeForm.post = (args: { transcript: string | { uuid: string } } | [transcript: string | { uuid: string } ] | string | { uuid: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

const summaries = {
    store: Object.assign(store, store),
}

export default summaries