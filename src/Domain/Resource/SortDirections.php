<?php

/*
 * Copyright (c) 2017-present trivago GmbH
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Trivago\Jade\Domain\Resource;

class SortDirections
{
    const ASC = 'asc';
    const DESC = 'desc';

    const ALL = [
        self::ASC,
        self::DESC,
    ];

    /**
     * @param string $direction
     *
     * @return bool
     */
    public static function isValid($direction)
    {
        return in_array($direction, self::ALL, true);
    }
}