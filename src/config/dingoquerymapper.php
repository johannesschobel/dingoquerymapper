<?php

return [

    /*
     * Are filter queries allowed? If set to true, queries like age>18 are allowed
     */
    'allowFilters' => true,

    /*
     * The default values
     */
    'defaults' => [
        'limit' => 15,
        'sort' => [
            [
                'column'    => 'id',
                'direction' => 'asc'
            ]
        ],
    ],

    /*
     * The parameters to be excluded
     */
    'excludedParameters' => [
        'include',          // because of Fractal Transformers
        'token',            // because of JWT Auth
    ],

];
