# Security Policy

## Supported versions

Security fixes are applied to the latest tagged release of `voodflow/vmedia` on
the `main` branch. Older releases may be asked to upgrade before receiving a
fix.

## Reporting a vulnerability

Do not open a public GitHub issue for a suspected security vulnerability.

Email **dev@voodflow.com** with `VoodMedia security` in the subject. Include:

- the affected package version or commit;
- a description of the vulnerability and its impact;
- reproducible steps or a proof of concept, when safe to share privately;
- any known mitigations or public disclosures.

We aim to acknowledge reports within three business days. Please allow a
reasonable remediation window before public disclosure.

## Scope

In scope are vulnerabilities in code shipped by `voodflow/vmedia`, including
authorization bypasses, unsafe uploads or archive handling, cross-site
scripting, cross-site request forgery, and unintended media disclosure.

Issues caused exclusively by a compromised host application, insecure
deployment configuration, or third-party packages not maintained in this
repository are outside this policy's scope.
