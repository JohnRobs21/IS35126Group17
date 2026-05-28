<?php
// Prevent clickjacking
header('X-Frame-Options: DENY');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Force HTTPS
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

// XSS Protection (older browsers)
header('X-XSS-Protection: 1; mode=block');

// Referrer policy
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'");

// Permissions policy
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Remove PHP version header
header_remove('X-Powered-By');