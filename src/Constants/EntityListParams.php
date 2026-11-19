<?php

namespace Iquesters\Foundation\Constants;

class EntityListParams
{
    // Reserved query parameters
    const PARAM_OFFSET = 'offset';

    const PARAM_LENGTH = 'length';

    const PARAM_SORT = 'sort';

    const PARAM_FILTER = 'filter';

    const PARAM_SEARCH = 'search';

    // Upper bound for the `length` page-size parameter
    const MAX_LENGTH = 100;

    // Longer free-text search terms are cut to this length
    const MAX_SEARCH_LENGTH = 100;

    // Filter operators
    const OP_EQ = 'eq';

    const OP_NEQ = 'neq';

    const OP_GT = 'gt';

    const OP_GTE = 'gte';

    const OP_LT = 'lt';

    const OP_LTE = 'lte';

    const OP_LIKE = 'like';

    const OP_STARTS = 'starts';

    const OP_ENDS = 'ends';

    const OP_IN = 'in';

    // Sort directions
    const DIR_ASC = 'asc';

    const DIR_DESC = 'desc';
}
