<h2>What the import can do</h2>

<p>
    <strong>Import</strong> takes in an Excel (<code>.xlsx</code>) or CSV file to
    create managers, investors, purchases or account entries in one go. It is for
    taking over existing records, not for day-to-day entry.
</p>

<h2>The order matters</h2>

<p>
    The four families import in this order, and no other:
</p>

<ol>
    <li><strong>Managers</strong></li>
    <li><strong>Investors</strong></li>
    <li><strong>Share purchases</strong></li>
    <li><strong>Account entries</strong></li>
</ol>

<p>
    Each step rests on the previous one: a purchase needs its investor, an investor
    may be attached to their manager. Reversing the order makes the lines that
    reference what does not exist yet fail.
</p>

<h2>How it goes</h2>

<ol>
    <li><strong>Download the CSV template</strong> for the family concerned. It carries the expected headers and one example line to replace.</li>
    <li>Fill it with your data, keeping the first line as it is.</li>
    <li><strong>Drop the file.</strong> The application reads it and shows a check table.</li>
    <li>Read that table: it says, line by line, what will be created, what will be skipped and why.</li>
    <li>Confirm.</li>
</ol>

<p class="note">
    <strong>Nothing is recorded before you confirm.</strong> Dropping the file only
    reads it. Until you have confirmed the check table, the database has not changed —
    you can fix the file and start again as often as needed.
</p>

<h2>Reading the check table</h2>

<p>
    Every line of the file appears there with its fate. Refusals are explained: an
    investor not found, an unreadable date, a duplicate. Fix the source file rather
    than the database: you will keep a trace of what you imported.
</p>

<h2>What the import does not do</h2>

<ul>
    <li>It <strong>does not record dividends</strong>. They are calculated from the dividends screen, which replays the whole history and applies the edge rules.</li>
    <li>It does not overwrite an existing file: a duplicate is flagged, not merged.</li>
    <li>It does not guess an ambiguous date format. Write dates as <code>DD/MM/YYYY</code>.</li>
</ul>

<p class="note">
    <strong>Entries are recorded in the order of the file's lines.</strong> The
    balance after each entry is frozen at the moment it is written: a file whose lines
    are not in chronological order will produce disconcerting intermediate balances,
    even if the final balance is right. Sort by date before importing.
</p>
