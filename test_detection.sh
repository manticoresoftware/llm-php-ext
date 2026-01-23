#!/bin/bash
# Test script to verify static/shared auto-detection in config.m4

set -e

echo "=========================================="
echo "LLM Extension Build Mode Detection Test"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test 1: Default (shared)
echo -e "${BLUE}Test 1: Default build (should be shared)${NC}"
echo "./configure --enable-llm"
echo "Expected: shared (default)"
echo ""

# Test 2: PHP static build (auto-detect)
echo -e "${BLUE}Test 2: PHP static build (should auto-detect static)${NC}"
echo "./configure --disable-shared --enable-llm"
echo "Expected: static (PHP built with --disable-shared or --enable-static)"
echo ""

# Test 3: Explicit static flag
echo -e "${BLUE}Test 3: Explicit static flag (should be static)${NC}"
echo "./configure --enable-llm --with-llm-static"
echo "Expected: static (explicit --with-llm-static)"
echo ""

# Test 4: PHP enable-static (auto-detect)
echo -e "${BLUE}Test 4: PHP enable-static (should auto-detect static)${NC}"
echo "./configure --enable-static --enable-llm"
echo "Expected: static (PHP built with --disable-shared or --enable-static)"
echo ""

echo "=========================================="
echo "How to verify:"
echo "=========================================="
echo ""
echo "1. Run configure with one of the above options"
echo "2. Look for this line in output:"
echo "   checking whether to build llm extension as static or shared..."
echo ""
echo "3. After build, verify:"
echo ""
echo "   Static build:"
echo "   $ nm sapi/cli/php | grep get_module"
echo "   $ php -m | grep llm"
echo ""
echo "   Shared build:"
echo "   $ ls -lh modules/llm.so"
echo "   $ php -dextension=llm.so -m | grep llm"
echo ""
echo "=========================================="
echo "Example full test:"
echo "=========================================="
echo ""
echo "cd /path/to/php-src"
echo "cp -r /path/to/llm-php-ext ext/llm"
echo "./buildconf --force"
echo ""
echo "# Test static auto-detection"
echo "./configure --disable-shared --enable-llm 2>&1 | grep 'whether to build llm'"
echo "# Should output: static (PHP built with --disable-shared or --enable-static)"
echo ""
echo "make clean"
echo ""
echo "# Test shared default"
echo "./configure --enable-llm 2>&1 | grep 'whether to build llm'"
echo "# Should output: shared (default)"
echo ""
