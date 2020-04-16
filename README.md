android manifest parser
=========

This is a better way for parsing Android Apk's AndroidManifest.xml binary file.
It uses the PHP ZipArchive package to unzip the apk file, and then parse the AndroidManifest.xml in BINARY FORMAT.
We know, xml file is formatted, and the hex data can be mapped into trees.
The node values can access recursively.
That's it!
