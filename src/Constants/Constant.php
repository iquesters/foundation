<?php

namespace Iquesters\Foundation\Constants;

class Constant
{
    const ENCTYPE = 'multipart/form-data';

    const MODE_CREATE = 'create';
    const MODE_VIEW = 'view';
    const MODE_EDIT = 'edit';
    const MODE_DELETE = 'delete';

    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';

    const ACTION_TYPE_SUBMIT = 'submit';
    const ACTION_TYPE_CANCEL = 'cancel';
    const ACTION_ROUTE_DEFAULT = '#';

    const ELEMENT_TYPE_BUTTON = 'button';
    const ELEMENT_COLOR_SUCCESS = 'success';
    const ELEMENT_COLOR_SECONDARY = 'secondary';

    const HEADER_ICON_CREATE = 'fas fa-database';
    const HEADER_ICON_VIEW = 'fas fa-eye';
    const HEADER_ICON_EDIT = 'fas fa-pen';
    const HEADER_ICON_DELETE = 'fas fa-trash';

    const ACTION_ICON_SUBMIT = 'far fa-save';
    const ACTION_ICON_CANCEL = 'far fa-times-circle';

    const BREAKPOINTS = ['xs', 'sm', 'md', 'lg', 'xl', 'xxl'];
    const DEFAULT_SIZE = [
        'xs' => 12,
        'sm' => 12,
        'md' => 12,
        'lg' => 6,
        'xl' => 6,
        'xxl' => 4,
    ];
}
