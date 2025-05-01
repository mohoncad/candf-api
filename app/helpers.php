<?php






function string_to_list_formatter($str)
{
    return array_filter(explode(',',str_replace('"', '', $str)));
}

