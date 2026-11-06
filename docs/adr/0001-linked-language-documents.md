# Linked language documents

A website page has one package allowance and a separate document for each language.
Each language document retains the existing page JSON format, save guards and revision key.
Existing documents remain the default language, with their identifiers and addresses intact.
This avoids wrapping the editor's nested text, custom-block JSON strings and forms in a new
translation format. It costs more document rows, and layout edits after the first copy are independent.

The design kit, media and business facts are shared. Entries keep one item/category identity;
only their titles and text fields receive optional overrides. Settings translations are restricted
to text fields chosen by the site. A visitor can switch only to a published page counterpart.
