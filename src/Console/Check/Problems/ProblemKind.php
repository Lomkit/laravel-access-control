<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
enum ProblemKind: string
{
    case ControlFailure = 'control_failure';
    case DuplicateControl = 'duplicate_control';
    case UncontrolledModel = 'uncontrolled_model';
    case InvalidControlledBy = 'invalid_controlled_by';
    case StaleControlCache = 'stale_control_cache';
    case StaleWatchCache = 'stale_watch_cache';
    case UnresolvablePolicy = 'unresolvable_policy';
    case PolicyWithoutControl = 'policy_without_control';
    case PolicyForAnotherControl = 'policy_for_another_control';
    case SearchFreshness = 'search_freshness';
    case PerimeterWithoutSearchKeys = 'perimeter_without_search_keys';
    case UnsearchableBoundary = 'unsearchable_boundary';
    case UnsupportedSearchEngine = 'unsupported_search_engine';
    case PlainSearchable = 'plain_searchable';
    case PivotWithoutUsing = 'pivot_without_using';
}
