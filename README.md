FPDF is a fantastic script for writing PDF files in PHP. It is written by Olivier PLATHEY. The latest version was released on 2011-06-18 (version 1.7) and is quite old, so I have updated it to bring it inline with PHP 5. I have also modified it to comply with most PSR 1&2 standards.

- Class name now Fpdf
- Naming conventions changed for class variables (camelcase)
- Scropt added for variables
- Naming conventions changed for class methods (camelcase, no underscore for non-public methods)
- Scope added for functions - public and protected
- Indentation fixed
- General syntax cleanup

In theory you can drop this in to your site and it should just work, by only changing the class name. Functions are different case but PHP doesn't recognise this anyway. Yay, hacks! Please, feel free to further improve, as I just put it up here in case anyone needed it as a starting point.

Leonard Challis
http://twitter.com/LeonardChallis
