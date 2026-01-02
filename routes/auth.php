<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::middleware("guest")->group(function () {
    Route::get("/login", function () {
        return view("auth.login");
    })->name("login");

    Route::get("/register", function () {
        return view("auth.register");
    })->name("register");
});

Route::middleware("auth")->group(function () {
    Route::post("/logout", [AuthenticatedSessionController::class, "destroy"])
        ->name("logout");
});