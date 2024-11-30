# FINIS Framework and CMS

F.I.N.I.S. : Flatnux Is Now Infinitely Scalable




## Installation instructions (C.M.S.):

1) Copy include/config.vars.local.php.sample in config.vars.local.php
2) Upload all files in your website folder
3) Go to http://[your website]/
4) Run the wizard


## Creating applications in framework mode

```php
<?php
require_once "../finis/finis_framework.php";
$FINIS = new FINIS();
$FINIS->runSection("home");
```

## Creating python application in framework mode


./sections/home/section.py
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

./example.php
```php
<?php
require_once "../finis/finis_framework.php";
$FINIS = new FINIS();
$FINIS->runSection("home");

```
