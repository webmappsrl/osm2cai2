# Formatting Guidelines for Shippie Reviews

## Suggestion Format Requirements

### **Critical Format Rules:**
1. **Always start with exact line reference**: `Line X:` where X is the specific line number
2. **Include code snippets**: Show the problematic code with 2-3 lines of context
3. **Use syntax highlighting**: Wrap code in appropriate language blocks (```php, ```js, etc.)
4. **Reference rule categories**: Mention which specific rule is violated
5. **Provide actionable solutions**: Include example of corrected code when possible

### **Severity Indicators:**
- 🚨 **HIGH**: Security vulnerabilities, critical bugs
- 🔥 **HIGH**: Breaking changes, data loss risks  
- ⚡ **MEDIUM**: Performance issues, N+1 queries
- 🎯 **MEDIUM**: Laravel best practices violations
- 🧹 **MEDIUM**: Clean code violations
- 🔧 **LOW**: Maintainability improvements
- 💄 **LOW**: Style and formatting issues

### **Required Structure:**
```markdown
### [EMOJI] **SEVERITY** - Line X: [Brief Title]

**File:** `path/to/file.php:X`
**Rule:** [Rule Category] 
**Issue:** [Detailed explanation of the problem]

**Problematic Code:**
```php
// Lines X-Y context
$problematic = "code here";  // ❌ Issue explanation
```

**Solution:**
```php
// Lines X-Y corrected
$corrected = "code here";  // ✅ Better approach
```

**Why:** [Explanation of why this is important]
```

### **Code Snippet Guidelines:**
- Always include line numbers in comments
- Mark problematic lines with ❌ 
- Mark corrected lines with ✅
- Show 2-3 lines of context before/after the issue
- Use proper PHP/JavaScript/etc. syntax highlighting

### **Language-Specific Patterns:**

#### **PHP/Laravel:**
- Reference Laravel version compatibility
- Mention Eloquent vs raw SQL implications
- Include namespace and use statements when relevant
- Reference PSR standards when applicable

#### **JavaScript/TypeScript:**
- Include ES6+ syntax preferences
- Reference Vue.js patterns if applicable
- Include async/await vs Promise patterns

### **Example Good Suggestion:**
```markdown
### 🚨 **HIGH** - Line 45: SQL Injection in raw query

**File:** `app/Console/Commands/SyncCommand.php:45`
**Rule:** Security > SQL Security
**Issue:** Raw SQL concatenation creates SQL injection vulnerability

**Problematic Code:**
```php
// Lines 43-47
$userId = $request->input('user_id');
$query = "SELECT * FROM users WHERE id = " . $userId;  // ❌ SQL Injection risk
$result = DB::select($query);
```

**Solution:**
```php
// Lines 43-47  
$userId = $request->input('user_id');
$result = DB::select('SELECT * FROM users WHERE id = ?', [$userId]);  // ✅ Safe parameter binding
// Or better: User::find($userId);  // ✅ Use Eloquent
```

**Why:** String concatenation allows malicious input to modify the SQL query structure, potentially exposing or manipulating data.
```

### **Reference Standards:**
- Always reference specific rules from our custom rule files
- Link to Laravel documentation when appropriate  
- Include performance impact estimates when relevant
- Suggest specific refactoring patterns for complex issues 