def isAcyclic1(head):
    """
    >>> e1=Element(1)
    >>> e2=Element(2)
    >>> e3=Element(3)
    >>> e4=Element(4)
    >>> e5=Element(5)
    >>> e1.next=e2
    >>> e2.next=e3
    >>> e3.next=e4
    >>> e4.next=e5
    >>> isAcyclic1(e1)
    True
    >>> e5.next=e3
    >>> isAcyclic1(e1)
    False
    >>> e5.next=e4
    >>> isAcyclic1(e1)
    False
    >>> e5.next=e2
    >>> isAcyclic1(e1)
    False
    """
    curr=head
    while curr!=None:
        sweeper=head
        while sweeper!=curr:
            if curr.next==sweeper:
                return False
            sweeper=sweeper.next
        curr=curr.next
    return True

class Element:
    def __init__(self,data,next=None):
        self.data=data
        self.next=next

if __name__ == "__main__":
    import doctest
    doctest.testmod()


