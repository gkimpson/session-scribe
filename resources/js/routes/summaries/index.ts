import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\SummaryController::show
* @see app/Http/Controllers/SummaryController.php:10
* @route '/summaries/{summary}'
*/
export const show = (args: { summary: number | { id: number } } | [summary: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/summaries/{summary}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SummaryController::show
* @see app/Http/Controllers/SummaryController.php:10
* @route '/summaries/{summary}'
*/
show.url = (args: { summary: number | { id: number } } | [summary: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { summary: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { summary: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            summary: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        summary: typeof args.summary === 'object'
        ? args.summary.id
        : args.summary,
    }

    return show.definition.url
            .replace('{summary}', parsedArgs.summary.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SummaryController::show
* @see app/Http/Controllers/SummaryController.php:10
* @route '/summaries/{summary}'
*/
show.get = (args: { summary: number | { id: number } } | [summary: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SummaryController::show
* @see app/Http/Controllers/SummaryController.php:10
* @route '/summaries/{summary}'
*/
show.head = (args: { summary: number | { id: number } } | [summary: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SummaryController::show
* @see app/Http/Controllers/SummaryController.php:10
* @route '/summaries/{summary}'
*/
const showForm = (args: { summary: number | { id: number } } | [summary: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SummaryController::show
* @see app/Http/Controllers/SummaryController.php:10
* @route '/summaries/{summary}'
*/
showForm.get = (args: { summary: number | { id: number } } | [summary: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SummaryController::show
* @see app/Http/Controllers/SummaryController.php:10
* @route '/summaries/{summary}'
*/
showForm.head = (args: { summary: number | { id: number } } | [summary: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

const summaries = {
    show: Object.assign(show, show),
}

export default summaries