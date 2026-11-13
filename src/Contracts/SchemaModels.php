<?php

namespace Iquesters\Foundation\Contracts;

/**
 * Supplies the Eloquent model classes that store generated form and table
 * schemas. Foundation generates schemas but does not own their storage;
 * the UI package (iquesters/user-interface) binds an implementation.
 */
interface SchemaModels
{
    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public function formSchemaModel(): string;

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public function tableSchemaModel(): string;
}
