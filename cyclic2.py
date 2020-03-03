def isAcyclic(head):

    """
    >>> e1=Element(1)
    >>> e2=Element(2)
    >>> e3=Element(3)
    >>> e4=Element(4)
    >>> e5=Element(5)
    >>> e1.next=e2
    >>> isAcyclic(e1)
    True
    >>> e2.next=e3
    >>> e3.next=e4
    >>> e4.next=e5
    >>> isAcyclic(e1)
    True
    >>> e5.next=e3
    >>> isAcyclic(e1)
    False
    >>> e5.next=e4
    >>> isAcyclic(e1)
    False
    >>> e5.next=e2
    >>> isAcyclic(e1)
    False
    """
    slow=head
    fast=head
    while fast!=None and fast.next!=None:
        slow=slow.next
        fast=fast.next.next
        if (fast==slow):
            return False
    return True

class Element:
    def __init__(self,data,next=None):
        self.data=data
        self.next=next

if __name__ == "__main__":
    import doctest
    doctest.testmod()
