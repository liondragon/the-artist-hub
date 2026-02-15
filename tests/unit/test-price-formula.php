<?php
/**
 * Unit Tests for TAH_Price_Formula
 */

// Load Class Under Test
require_once dirname(__DIR__, 2) . '/inc/modules/pricing/class-price-formula.php';

// Test Parse: Default
function test_parse_default()
{
    $result = TAH_Price_Formula::parse('$');
    Assert::same(TAH_Price_Formula::MODE_DEFAULT, $result['mode'], 'Parse "$" mode');
    Assert::same(0.0, $result['modifier'], 'Parse "$" modifier');

    $result = TAH_Price_Formula::parse('');
    Assert::same(TAH_Price_Formula::MODE_DEFAULT, $result['mode'], 'Parse empty string mode');
}

// Test Parse: Addition
function test_parse_addition()
{
    $result = TAH_Price_Formula::parse('$ +100');
    Assert::same(TAH_Price_Formula::MODE_ADDITION, $result['mode'], 'Parse "$ +100" mode');
    Assert::same(100.0, $result['modifier'], 'Parse "$ +100" modifier');

    $result = TAH_Price_Formula::parse('$ -50.50');
    Assert::same(TAH_Price_Formula::MODE_ADDITION, $result['mode'], 'Parse "$ -50.50" mode');
    Assert::same(-50.50, $result['modifier'], 'Parse "$ -50.50" modifier');
}

// Test Parse: Percentage
function test_parse_percentage()
{
    $result = TAH_Price_Formula::parse('$ *1.1');
    Assert::same(TAH_Price_Formula::MODE_PERCENTAGE, $result['mode'], 'Parse "$ *1.1" mode');
    Assert::same(1.1, $result['modifier'], 'Parse "$ *1.1" modifier');

    $result = TAH_Price_Formula::parse('$ *0.9');
    Assert::same(TAH_Price_Formula::MODE_PERCENTAGE, $result['mode'], 'Parse "$ *0.9" mode');
    Assert::same(0.9, $result['modifier'], 'Parse "$ *0.9" modifier');
}

// Test Parse: Override
function test_parse_override()
{
    $result = TAH_Price_Formula::parse('1500');
    Assert::same(TAH_Price_Formula::MODE_OVERRIDE, $result['mode'], 'Parse "1500" mode');
    Assert::same(1500.0, $result['modifier'], 'Parse "1500" modifier');
}

// Test Resolve: Logic
function test_resolve_logic()
{
    // Default
    $price = TAH_Price_Formula::resolve(TAH_Price_Formula::MODE_DEFAULT, 0, 100.00);
    Assert::same(100.00, $price, 'Resolve Default');

    // Addition
    $price = TAH_Price_Formula::resolve(TAH_Price_Formula::MODE_ADDITION, 50, 100.00);
    Assert::same(150.00, $price, 'Resolve Addition +50');

    // Percentage
    $price = TAH_Price_Formula::resolve(TAH_Price_Formula::MODE_PERCENTAGE, 1.1, 100.00);
    Assert::same(110.00, $price, 'Resolve Percentage *1.1');

    // Override
    $price = TAH_Price_Formula::resolve(TAH_Price_Formula::MODE_OVERRIDE, 500, 100.00);
    Assert::same(500.00, $price, 'Resolve Override 500');
}

// Test Resolve: Rounding
function test_resolve_rounding()
{
    // Rounding Nearest .05
    // 10.02 -> 10.00
    Assert::same(10.00, TAH_Price_Formula::resolve(TAH_Price_Formula::MODE_OVERRIDE, 10.02, 0, 0.05), 'Round 10.02 to nearest 0.05');
    // 10.03 -> 10.05
    Assert::same(10.05, TAH_Price_Formula::resolve(TAH_Price_Formula::MODE_OVERRIDE, 10.03, 0, 0.05), 'Round 10.03 to nearest 0.05');

    // Rounding UP .99
    // 10.01 -> 10.99
    // Logic: ceil(10.01 / 1) * 1 = 11? No, TAH formula might be slightly different. 
    // Let's check logic: apply_mround(10.01, 1, 'up') -> ceil(10.01)*1 = 11. 
    // Wait, mround logic used is: round(ceil($scaled) * $multiple, 2)

    // Testing specific business requirement: Price ending in .99?
    // User might pass 1.0 as multiple + ROUNDING_UP? No, standard is usually 0.01, 0.05, 1.0. 

    // Test simple UP rounding
    Assert::same(11.00, TAH_Price_Formula::resolve(TAH_Price_Formula::MODE_OVERRIDE, 10.1, 0, 1.0, TAH_Price_Formula::ROUNDING_UP), 'Round 10.1 UP to nearest 1.0');
}

// Run Tests
test_parse_default();
test_parse_addition();
test_parse_percentage();
test_parse_override();
test_resolve_logic();
test_resolve_rounding();
