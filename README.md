# FINIS Framework and CMS

F.I.N.I.S.: Flatnux Is Now Infinitely Scalable

## Installation instructions (C.M.S.):

1. Copy `include/config.vars.local.php.sample` to `config.vars.local.php`
2. Upload all files to your website folder
3. Go to `http://[your website]/`
4. Run the wizard

## Creating applications in framework mode
`./sections/home/section.en.html`
```html
<h1>FINIS !!!</h1>
```

`./sections/home/section.php`
```php
<?php
global $_FN;
echo "<h1>FINIS !!!</h1>";
echo $_FN['siteurl'];
```

`example.php`
```php
<?php
require_once "../finis/finis_framework.php";
$FINIS = new FINIS();
$FINIS->runSection("home");
```

## Creating Python application in framework mode

`./sections/home/section.py`
```python
import json
import sys

json_data = sys.argv[1]
# Parse the JSON data
FN = json.loads(json_data)
print(f"""
Site url: {FN['siteurl']}
""")
```

`./example.php`
```php
<?php
require_once "../finis/finis_framework.php";
$FINIS = new FINIS();
$FINIS->runSection("home");
```