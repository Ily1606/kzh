<?php

namespace App\Enums;

enum ApiMessage: string
{
    case REQUEST_SUCCESSFUL = 'Request successful.';
    case REGISTRATION_SUCCESSFUL = 'Registration successful.';
    case LOGIN_SUCCESSFUL = 'Login successful.';
    case LOGOUT_SUCCESSFUL = 'Logout successful.';
    case USER_RETRIEVED = 'User retrieved successfully.';
    case HEALTH_CHECK_SUCCESSFUL = 'Health check successful.';
    case INVALID_DATA = 'The given data was invalid.';
    case INVALID_CREDENTIALS = 'Email or password is incorrect.';
    case UNAUTHENTICATED = 'Unauthenticated.';
    case UNAUTHORIZED = 'This action is unauthorized.';
    case NOT_FOUND = 'Resource not found.';
    case INVALID_TOKEN = 'The request token is invalid or expired.';
    case REQUEST_FAILED = 'The request could not be completed.';
    case UNEXPECTED_ERROR = 'An unexpected error occurred.';
}
