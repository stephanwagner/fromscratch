#!/bin/bash

# Delete all .DS_Store files recursively
find . -name ".DS_Store" -exec rm -f {} \;

# Compile languages (all German variants use the same translations as de_DE)
LANGUAGE_DIR="./themes/fromscratch/languages"

cp "$LANGUAGE_DIR/fromscratch-de_DE.po" "$LANGUAGE_DIR/fromscratch-de_DE_formal.po"
cp "$LANGUAGE_DIR/fromscratch-de_DE.po" "$LANGUAGE_DIR/fromscratch-de_AT.po"
cp "$LANGUAGE_DIR/fromscratch-de_DE.po" "$LANGUAGE_DIR/fromscratch-de_CH.po"
cp "$LANGUAGE_DIR/fromscratch-de_DE.po" "$LANGUAGE_DIR/fromscratch-de_CH_informal.po"

perl -pi -e 's/"Language: de_DE\\n"/"Language: de_DE_formal\\n"/' "$LANGUAGE_DIR/fromscratch-de_DE_formal.po"
perl -pi -e 's/"Language: de_DE\\n"/"Language: de_AT\\n"/' "$LANGUAGE_DIR/fromscratch-de_AT.po"
perl -pi -e 's/"Language: de_DE\\n"/"Language: de_CH\\n"/' "$LANGUAGE_DIR/fromscratch-de_CH.po"
perl -pi -e 's/"Language: de_DE\\n"/"Language: de_CH_informal\\n"/' "$LANGUAGE_DIR/fromscratch-de_CH_informal.po"

find "$LANGUAGE_DIR" -type f -name "*.po" | while read -r po; do
    # Get the filename without the path and extension
    filename=$(basename "$po" .po)
    # Get the directory path of the .po file
    dirpath=$(dirname "$po")
    # Compile the .po file into a .mo file in the same directory
    msgfmt -o "$dirpath/$filename.mo" "$po"
done

echo -e "\033[32m✔ PO files compiled successfully.\033[0m"
